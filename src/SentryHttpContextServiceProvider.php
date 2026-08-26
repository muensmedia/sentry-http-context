<?php

namespace Muensmedia\SentryHttpContext;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Muensmedia\SentryHttpContext\Http\SentryBreadcrumbMiddleware;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SentryHttpContextServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sentry-http-context')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        // Has to happen during register(): every provider is registered before
        // any is booted, so this lands before sentry-laravel boots its features
        // and memoises whether the breadcrumb feature is on.
        if (config('sentry-http-context.replace_sentry_breadcrumbs')) {
            config(['sentry.breadcrumbs.http_client_requests' => false]);
        }
    }

    public function packageBooted(): void
    {
        $this->registerDescribeMacro();

        if (config('sentry-http-context.presets.enabled')) {
            $this->registerPresets();
        }

        if (config('sentry-http-context.breadcrumbs.enabled')) {
            Http::globalMiddleware(new SentryBreadcrumbMiddleware(
                (int) config('sentry-http-context.breadcrumbs.max_body_length'),
                (array) config('sentry-http-context.breadcrumbs.redacted_headers', [])
            ));
        }
    }

    /**
     * Label the breadcrumbs of a single request:
     *
     *     Http::describe('Sync customer')->post($url, $payload);
     *
     * The description rides along as a request option, which is the only channel
     * that reaches the Guzzle handler stack where the breadcrumbs are recorded.
     */
    private function registerDescribeMacro(): void
    {
        PendingRequest::macro('describe', function (string $description) {
            /** @var PendingRequest $this */
            return $this->withOptions([
                SentryBreadcrumbMiddleware::DESCRIPTION_OPTION => $description,
            ]);
        });
    }

    /**
     * Defaults for every pending request. These are defaults rather than
     * overrides - anything the caller sets explicitly is applied afterwards and
     * wins.
     *
     * The closure is evaluated per request, so both the config and any
     * SentryHttpContext override stay live no matter when they are set.
     */
    private function registerPresets(): void
    {
        Http::globalOptions(static function (): array {
            $config = config('sentry-http-context.presets');

            $headers = array_filter([
                'User-Agent' => SentryHttpContext::userAgent(),
                'Accept' => ($config['accept_json'] ?? false) ? 'application/json' : null,
            ]);

            return array_filter([
                'headers' => $headers,
                'timeout' => $config['timeout'] ?? null,
            ]);
        });
    }
}
