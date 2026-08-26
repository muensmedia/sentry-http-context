<?php

use Illuminate\Support\Facades\Http;
use Muensmedia\SentryHttpContext\SentryHttpContext;

beforeEach(function () {
    Http::fake();
});

function sentUserAgent(): ?string
{
    $sent = null;

    Http::assertSent(function ($request) use (&$sent) {
        $sent = $request->header('User-Agent')[0] ?? null;

        return true;
    });

    return $sent;
}

it('derives a user agent from the application', function () {
    config()->set('app.name', 'Acme');
    config()->set('app.url', 'https://acme.test');

    Http::get('https://example.com');

    expect(sentUserAgent())->toBe('Acme (ENV: testing; URL: https://acme.test)');
});

it('takes a fixed user agent from the config', function () {
    config()->set('sentry-http-context.presets.user_agent', 'Configured/2.0');

    Http::get('https://example.com');

    expect(sentUserAgent())->toBe('Configured/2.0');
});

it('lets a service provider override the user agent', function () {
    SentryHttpContext::useUserAgent('Overridden/3.0');

    Http::get('https://example.com');

    expect(sentUserAgent())->toBe('Overridden/3.0');
});

it('resolves an overriding closure per request', function () {
    SentryHttpContext::useUserAgent(fn () => 'Acme/'.config('app.version'));

    config()->set('app.version', '1.0');
    Http::get('https://example.com');
    expect(sentUserAgent())->toBe('Acme/1.0');

    config()->set('app.version', '2.0');
    Http::get('https://example.com');
    expect(sentUserAgent())->toBe('Acme/2.0');
});

it('prefers the override over the config', function () {
    config()->set('sentry-http-context.presets.user_agent', 'Configured/2.0');
    SentryHttpContext::useUserAgent('Overridden/3.0');

    Http::get('https://example.com');

    expect(sentUserAgent())->toBe('Overridden/3.0');
});

it('sends no user agent when the override is null', function () {
    SentryHttpContext::useUserAgent(null);

    Http::get('https://example.com');

    expect(sentUserAgent())->not->toContain('ENV:');
});

it('sends no user agent when the config is false', function () {
    config()->set('sentry-http-context.presets.user_agent', false);

    Http::get('https://example.com');

    expect(sentUserAgent())->not->toContain('ENV:');
});

it('applies the accept header', function () {
    Http::get('https://example.com');

    Http::assertSent(fn ($request) => $request->header('Accept') === ['application/json']);
});

it('lets the caller override the user agent per request', function () {
    Http::withUserAgent('Custom/1.0')->get('https://example.com');

    expect(sentUserAgent())->toBe('Custom/1.0');
});
