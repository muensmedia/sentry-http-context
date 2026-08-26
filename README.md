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

<img src="./.github/request-breadcrumbs-in-sentry.png" alt="A request and its response in Sentry's breadcrumb trail, with the payload and headers expanded">

Because the breadcrumbs are typed as `http`, Sentry renders the method and URL
on their own line and makes the rest expandable — the payload and the outgoing
headers are one click away, right above the exception that made you look.

## Labelling a request

Every breadcrumb carries an optional message — the line under the category in
the screenshot above. This package leaves it empty by default, so a request
shows up as `HTTP Request` and its URL, nothing else:

| Category | Message |
| --- | --- |
| `HTTP Request` | — |
| `HTTP Response` | — |

`describe()` fills that in:

```php
Http::describe('Sync customer to CRM')
    ->post('https://api.example.com/customers', $payload);
```

| Category | Message |
| --- | --- |
| `HTTP Request` | Sync customer to CRM |
| `HTTP Response` | Sync customer to CRM |

Both breadcrumbs of the same call get the same label, so when an exception is
reported the trail above it reads as a sequence of steps in your own words
instead of a list of URLs. Worth it wherever the endpoint alone does not say what
the call was for — a shared gateway, a webhook, a retry.

It is a macro on `PendingRequest`, so it chains anywhere:

```php
Http::withToken($token)
    ->describe('Sync customer to CRM')
    ->timeout(5)
    ->post($url, $payload);
```

The label travels as a Guzzle request option rather than a header, so it never
leaves your application, and it applies to that one request only — the next call
is unlabelled again unless you say otherwise.

## Presets

Every pending request gets a set of defaults:

- `User-Agent`, derived from the application: `MyApp (ENV: production; URL: https://myapp.test)`
- `Accept: application/json`
- `timeout: 60`

These are defaults, not overrides — whatever you set on the request itself wins.
Turn the whole set off with `SENTRY_HTTP_CONTEXT_PRESETS=false`.

### Changing the user agent

Nobody has to live with ours. If a fixed string is all you need, put it in your
`.env` and you are done:

```dotenv
SENTRY_HTTP_CONTEXT_USER_AGENT="Acme/1.0"
```

For anything that has to be computed, call `useUserAgent()` from a service
provider — `AppServiceProvider` is the usual place:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Muensmedia\SentryHttpContext\SentryHttpContext;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        SentryHttpContext::useUserAgent(fn () => 'Acme/'.config('app.version'));
    }
}
```

The closure runs per request, not at boot, so it can read config, the current
environment or anything else that needs a booted application — and it does not
matter whether your provider boots before or after ours.

The three sources apply in this order:

1. `SentryHttpContext::useUserAgent()`
2. the `presets.user_agent` config value, or `SENTRY_HTTP_CONTEXT_USER_AGENT`
3. otherwise derived from the application: `MyApp (ENV: production; URL: https://myapp.test)`

To send no user agent at all and leave Guzzle's own in place, pass `null` to
`useUserAgent()` or set the config value to `false`. On a single request,
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

### Against a specific PHP version

The package supports PHP 8.2 – 8.5, and there is no committed lock file — a
library should not pin one dependency set. Each version therefore resolves its
own: 8.2 lands on Pest 3 and Symfony 7, the newer ones on Pest 4 and Symfony 8.

The practical consequence is that `vendor/` is not portable between versions. It
has to be rebuilt whenever you switch, which is what the `rm -rf` below is for:

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html wodby/php:8.2 \
    sh -c 'rm -rf vendor composer.lock && composer update --no-interaction && vendor/bin/pest'
```

Swap `8.2` for `8.3`, `8.4` or `8.5`. Since the working directory is mounted,
this overwrites your local `vendor/` — run the version you develop on last, or
`composer update` again afterwards.

For a shell to poke around in instead:

```bash
docker run --rm -it -v "$(pwd)":/var/www/html -w /var/www/html wodby/php:8.2 bash
```

`composer install` is not an option here. Without a lock file it behaves like
`update` anyway, and with one left over from another version it fails outright:
the 8.5 set pulls `symfony/clock`, which requires PHP >= 8.4, so installing that
lock on 8.3 stops at *"Your lock file does not contain a compatible set of
packages"*.

The same thing runs in CI, once per version in the matrix, plus `pint --test`.

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
