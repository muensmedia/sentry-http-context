<?php

namespace Muensmedia\SentryHttpContext\Tests;

use Illuminate\Support\Facades\Http;
use Muensmedia\SentryHttpContext\SentryHttpContext;
use Muensmedia\SentryHttpContext\SentryHttpContextServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;
use Sentry\SentrySdk;
use Sentry\State\Scope;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the network: anything not explicitly faked throws.
        Http::preventStrayRequests();

        // The override is static, so it would otherwise leak between tests.
        SentryHttpContext::useDefaultUserAgent();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            SentryServiceProvider::class,
            SentryHttpContextServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'SentryHttpContext');
        $app['config']->set('app.url', 'http://localhost');

        // The DSN only needs to be syntactically valid: a client has to exist for
        // the hub to accept breadcrumbs at all, but nothing is ever transmitted
        // because no event is captured and the hub is never flushed.
        $app['config']->set('sentry.dsn', 'https://publickey@sentry.example.com/1');
        $app['config']->set('sentry.traces_sample_rate', 1.0);
        $app['config']->set('sentry.trace_propagation_targets', null);
    }

    /**
     * The breadcrumbs currently recorded on the Sentry scope.
     *
     * The scope keeps them private, so we apply it to a throwaway event and read
     * them back off that.
     *
     * @return Breadcrumb[]
     */
    public function sentryBreadcrumbs(): array
    {
        $event = Event::createEvent();

        SentrySdk::getCurrentHub()->configureScope(
            static fn (Scope $scope) => $scope->applyToEvent($event)
        );

        return $event->getBreadcrumbs();
    }
}
