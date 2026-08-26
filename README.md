# sentry-http-context

Adds full request/response context for every outgoing Laravel HTTP client call to
your Sentry breadcrumb trail.

## Installation

```bash
composer require muensmedia/sentry-http-context
```

That is the whole integration. The service provider is auto-discovered and hooks
itself into Laravel's HTTP client globally — **your existing `Http::get()` /
`Http::post()` calls stay exactly as they are.**

```php
Http::post('https://api.example.com/orders', ['sku' => 'ABC']);
```

lands in Sentry as two breadcrumbs:

| Category | Payload |
| --- | --- |
| `HTTP Request` | method, url, headers (credentials masked), decoded body |
| `HTTP Response` | method, url, status, decoded body (level `warning` from 4xx up) |

Connection failures are recorded as `HTTP Failure` at level `error`.

## Labelling a request

The breadcrumb message is optional and set per request:

```php
Http::describe('Sync customer to CRM')
    ->post('https://api.example.com/customers', $payload);
```

`describe()` is a macro on `PendingRequest`, so it chains anywhere in the fluent
chain. The label travels as a Guzzle request option, not as a header — it never
leaves your application.

## Presets

Every pending request gets a set of defaults:

- `User-Agent: {app.name} (ENV: {environment}; URL: {app.url})`
- `Accept: application/json`
- `timeout: 60`

These are defaults, not overrides — `Http::withUserAgent()`, `->timeout()` and
`->replaceHeaders()` win. Note that `->withHeader('User-Agent', …)` *merges*
rather than replaces (a Laravel quirk of `array_merge_recursive`); use
`->withUserAgent()` or `->replaceHeaders()` to replace cleanly.

Turn them off with `SENTRY_HTTP_CONTEXT_PRESETS=false`, or pin a fixed agent with
`SENTRY_HTTP_CONTEXT_USER_AGENT`.

### Changing the user agent

Nobody has to live with ours. Three ways, in order of precedence:

```php
// 1. From any service provider's boot() - resolved per request, so it may read
//    anything that needs a booted application.
SentryHttpContext::useUserAgent(fn () => 'Acme/'.config('app.version'));

// 2. A fixed string in the config, or SENTRY_HTTP_CONTEXT_USER_AGENT.
'user_agent' => 'Acme/1.0',

// 3. Unset - derived from the application:
//    "{app.name} (ENV: {environment}; URL: {app.url})"
```

Pass `null` to `useUserAgent()` (or set the config to `false`) to send none at
all and leave Guzzle's own in place. Per request, `Http::withUserAgent()` still
wins over all of it.

## Configuration

```bash
php artisan vendor:publish --tag=sentry-http-context-config
```

| Key | Default | |
| --- | --- | --- |
| `breadcrumbs.enabled` | `true` | record breadcrumbs at all |
| `breadcrumbs.max_body_length` | `4096` | truncation for non-JSON bodies |
| `breadcrumbs.redacted_headers` | `authorization`, `cookie`, … | masked before shipping |
| `presets.enabled` | `true` | apply the defaults above |
| `presets.user_agent` | `null` | `null` = derive it, see above |
| `replace_sentry_breadcrumbs` | `true` | see below |

### Relationship to sentry-laravel

`sentry-laravel` already writes its own, leaner breadcrumb for each HTTP client
response. Leaving `replace_sentry_breadcrumbs` on disables those so requests do
not appear twice in the trail. Sentry's **distributed tracing** for HTTP client
calls is untouched — `sentry-trace` and `baggage` headers are still attached by
`sentry-laravel` itself.

## Testing

```bash
composer test
```
