<?php

use Illuminate\Support\Facades\Http;
use Sentry\Breadcrumb;

const BREADCRUMB_URL = 'https://example.com/breadcrumbs';

/**
 * Each test registers its own stub: Http::fake() merges into the existing stub
 * list and the first match wins, so a shared beforeEach() fake would shadow
 * anything a test tries to set up afterwards.
 */
function fakeEndpoint(mixed $body = ['ok' => true], int $status = 200): void
{
    Http::fake([BREADCRUMB_URL => Http::response($body, $status)]);
}

it('records a breadcrumb for the request and the response', function () {
    fakeEndpoint();

    Http::post(BREADCRUMB_URL, ['test' => 'value']);

    $breadcrumbs = $this->sentryBreadcrumbs();

    expect($breadcrumbs)->toHaveCount(2);

    [$request, $response] = $breadcrumbs;

    expect($request->getCategory())->toBe('HTTP Request')
        ->and($request->getType())->toBe(Breadcrumb::TYPE_HTTP)
        ->and($request->getMetadata())->toMatchArray([
            'method' => 'POST',
            'url' => BREADCRUMB_URL,
            'data' => ['test' => 'value'],
        ]);

    expect($response->getCategory())->toBe('HTTP Response')
        ->and($response->getType())->toBe(Breadcrumb::TYPE_HTTP)
        ->and($response->getLevel())->toBe(Breadcrumb::LEVEL_INFO)
        ->and($response->getMetadata())->toMatchArray([
            'method' => 'POST',
            'url' => BREADCRUMB_URL,
            'status' => 200,
            'response' => ['ok' => true],
        ]);
});

it('leaves the request body intact for the transport', function () {
    fakeEndpoint();

    Http::post(BREADCRUMB_URL, ['test' => 'value']);

    Http::assertSent(fn ($request) => $request->body() === '{"test":"value"}');
});

it('leaves the response body readable for the caller', function () {
    fakeEndpoint();

    $response = Http::post(BREADCRUMB_URL, ['test' => 'value']);

    expect($response->json())->toBe(['ok' => true])
        ->and($response->status())->toBe(200);
});

it('flags error responses as warnings', function () {
    fakeEndpoint(['error' => 'nope'], 422);

    Http::post(BREADCRUMB_URL, ['test' => 'value']);

    expect($this->sentryBreadcrumbs()[1]->getLevel())->toBe(Breadcrumb::LEVEL_WARNING);
});

it('keeps a non-json response body as a truncated string', function () {
    fakeEndpoint(str_repeat('a', 10_000));

    Http::post(BREADCRUMB_URL, ['test' => 'value']);

    $body = $this->sentryBreadcrumbs()[1]->getMetadata()['response'];

    expect($body)->toBeString()
        ->and(strlen($body))->toBeLessThanOrEqual(4096);
});

it('redacts credential headers', function () {
    fakeEndpoint();

    Http::withToken('super-secret')->post(BREADCRUMB_URL, ['test' => 'value']);

    $headers = $this->sentryBreadcrumbs()[0]->getMetadata()['headers'];

    expect($headers['Authorization'])->toBe('[redacted]');
});

it('still sends the untouched header to the transport', function () {
    fakeEndpoint();

    Http::withToken('super-secret')->post(BREADCRUMB_URL, ['test' => 'value']);

    Http::assertSent(fn ($request) => $request->header('Authorization') === ['Bearer super-secret']);
});
