<div align="center">

<img src="./.github/banner.png" alt="Sentry HTTP Context: Colorful 19:4 banner for muensmedia/sentry-http-context: Laravel HTTP request and description cards flow via an orange arrow into the Sentry logo, with a panel showing method, URL, headers, and data.">

# Sentry HTTP Context

**A [MÜNSMEDIA](https://muensmedia.de) project.**

This repo is **not affiliated with, endorsed by, or maintained by Sentry.**

</div>

Adds full request/response context for every outgoing Laravel HTTP client call to
your Sentry breadcrumb trail.

## Requirements

- PHP 8.2 – 8.5
- Laravel 11 or 12
- [`sentry/sentry-laravel`](https://github.com/getsentry/sentry-laravel) 4.27+,
  already set up in your application

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

| Category | Level | Metadata |
| --- | --- | --- |
| `HTTP Request` | `info` | `method`, `url`, `headers` (credentials masked), `data` |
| `HTTP Response` | `info`, `warning` from 4xx up | `method`, `url`, `status`, `response` |
| `HTTP Failure` | `error` | `method`, `url`, `reason` |

`data` and `response` are the decoded body where Laravel can decode it (JSON or
form-encoded), and a truncated string otherwise.

## Labelling a request

The breadcrumb message is optional and set per request:

```php
Http::describe('Sync customer to CRM')
    ->post('https://api.example.com/customers', $payload);
```

`describe()` is a macro on `PendingRequest`, so it chains anywhere in the fluent
chain. The label travels as a Guzzle request option, not as a header — it never
leaves your application, and it does not carry over to the next request.

## Presets

Every pending request gets a set of defaults:

- `User-Agent`, derived from the application: `MyApp (ENV: production; URL: https://myapp.test)`
- `Accept: application/json`
- `timeout: 60`

These are defaults, not overrides — `Http::withUserAgent()`, `->timeout()` and
`->replaceHeaders()` win. Note that `->withHeader('User-Agent', …)` *merges*
rather than replaces (a Laravel quirk of `array_merge_recursive`); use
`->withUserAgent()` or `->replaceHeaders()` to replace cleanly.

Turn the whole set off with `SENTRY_HTTP_CONTEXT_PRESETS=false`.

### Changing the user agent

Nobody has to live with ours. Three ways, in order of precedence:

```php
use Muensmedia\SentryHttpContext\SentryHttpContext;

// 1. From any service provider's boot() — resolved per request, so it may read
//    anything that needs a booted application, and registration order is
//    irrelevant.
SentryHttpContext::useUserAgent(fn () => 'Acme/'.config('app.version'));

// 2. A fixed string, in config or via SENTRY_HTTP_CONTEXT_USER_AGENT.
'user_agent' => 'Acme/1.0',

// 3. Unset — derived from the application:
//    "MyApp (ENV: production; URL: https://myapp.test)"
```

To send no user agent at all and leave Guzzle's own in place, pass `null` to
`useUserAgent()` or set the config value to `false`. Per request,
`Http::withUserAgent()` still wins over all of it.

## Configuration

```bash
php artisan vendor:publish --tag=sentry-http-context-config
```

| Key | Default | Env | |
| --- | --- | --- | --- |
| `breadcrumbs.enabled` | `true` | `SENTRY_HTTP_CONTEXT_BREADCRUMBS` | record breadcrumbs at all |
| `breadcrumbs.max_body_length` | `4096` | `SENTRY_HTTP_CONTEXT_MAX_BODY_LENGTH` | hard cap for bodies that cannot be decoded |
| `breadcrumbs.redacted_headers` | `authorization`, `proxy-authorization`, `cookie`, `x-api-key`, `x-auth-token` | — | masked before shipping |
| `presets.enabled` | `true` | `SENTRY_HTTP_CONTEXT_PRESETS` | apply the defaults above |
| `presets.user_agent` | `null` | `SENTRY_HTTP_CONTEXT_USER_AGENT` | `null` = derive it, `false` = send none |
| `presets.accept_json` | `true` | — | send `Accept: application/json` |
| `presets.timeout` | `60` | — | seconds |
| `replace_sentry_breadcrumbs` | `true` | — | see below |

### Redaction

Request headers are shipped to Sentry verbatim, so anything carrying a
credential is masked as `[redacted]` first. The list is matched
case-insensitively and is yours to extend — the outgoing request itself is never
touched, only the breadcrumb.

Response bodies are **not** redacted. If an endpoint returns secrets, turn
breadcrumbs off for that part of your application rather than relying on the
header list.

### Relationship to sentry-laravel

`sentry-laravel` already writes its own, leaner breadcrumb for each HTTP client
response. Leaving `replace_sentry_breadcrumbs` on disables those so requests do
not appear twice in the trail. Sentry's **distributed tracing** for HTTP client
calls is untouched — `sentry-trace` and `baggage` headers are still attached by
`sentry-laravel` itself.

## How it works

The package registers a single Guzzle middleware via `Http::globalMiddleware()`,
plus defaults via `Http::globalOptions()`. It does not replace Laravel's
`Illuminate\Http\Client\Factory`, define a facade of its own, or require any
change at the call site.

The middleware sits on the handler stack rather than on Laravel's
`RequestSending` / `ResponseReceived` events for one reason: only the handler
stack is handed the request options, which is where the `describe()` label
travels. Request and response are correlated through the promise chain, so
concurrent pooled requests cannot mix up their labels.

## Testing

```bash
composer test
```

Runs on PHP 8.2 through 8.5. There is no committed lock file — a library should
not pin one dependency set, so CI resolves its own per PHP version (8.2 gets
Pest 3 and Symfony 7, the rest Pest 4 and Symfony 8).

## About

Built and maintained by [MÜNSMEDIA](https://muensmedia.de).

This is an independent, unofficial package. It is **not affiliated with,
endorsed by, or maintained by Sentry**, and it is not a Sentry product. It builds
on the official [`sentry/sentry-laravel`](https://github.com/getsentry/sentry-laravel)
SDK, which is untouched by this package and remains subject to its own licence
and terms. "Sentry" is a trademark of its respective owner and is used here only
to describe what this package integrates with.

## License

MIT — see [LICENSE](LICENSE).
