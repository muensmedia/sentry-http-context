<?php

use Illuminate\Support\Facades\Http;

const DESCRIBE_URL = 'https://example.com/webhook';

/**
 * @return array<int, string|null>
 */
function breadcrumbMessages(mixed $testCase): array
{
    return array_map(
        static fn ($breadcrumb) => $breadcrumb->getMessage(),
        $testCase->sentryBreadcrumbs()
    );
}

beforeEach(function () {
    Http::fake([DESCRIBE_URL => Http::response(['ok' => true])]);
});

it('labels both breadcrumbs with the description', function () {
    Http::describe('Webhook ping')->post(DESCRIBE_URL, ['test' => 'value']);

    expect(breadcrumbMessages($this))->toBe(['Webhook ping', 'Webhook ping']);
});

it('leaves the description empty when none is given', function () {
    Http::post(DESCRIBE_URL, ['test' => 'value']);

    expect(breadcrumbMessages($this))->toBe([null, null]);
});

it('does not leak the description into the next request', function () {
    Http::describe('Webhook ping')->post(DESCRIBE_URL, ['test' => 'value']);
    Http::post(DESCRIBE_URL, ['test' => 'value']);

    expect(breadcrumbMessages($this))->toBe(['Webhook ping', 'Webhook ping', null, null]);
});

it('does not send the description as a header', function () {
    Http::describe('Webhook ping')->post(DESCRIBE_URL, ['test' => 'value']);

    Http::assertSent(fn ($request) => ! $request->hasHeader('sentry_description'));
});
