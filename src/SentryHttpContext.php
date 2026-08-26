<?php

namespace Muensmedia\SentryHttpContext;

use Closure;
use Illuminate\Support\Facades\App;

/**
 * Entry point for overriding what the package sends.
 *
 * Call this from any service provider's boot() method - the values are resolved
 * per request, so registration order does not matter:
 *
 *     SentryHttpContext::useUserAgent(fn () => 'Acme/'.config('app.version'));
 */
class SentryHttpContext
{
    private static Closure|string|null $userAgent = null;

    private static bool $userAgentOverridden = false;

    /**
     * Replace the user agent sent with every request.
     *
     * A closure is resolved per request, so it may read config, the current
     * environment, or anything else that needs a booted application. Pass null
     * to send no user agent at all and leave Guzzle's own in place.
     */
    public static function useUserAgent(Closure|string|null $userAgent): void
    {
        self::$userAgent = $userAgent;
        self::$userAgentOverridden = true;
    }

    /**
     * Drop an override and fall back to the config value, then to the default.
     */
    public static function useDefaultUserAgent(): void
    {
        self::$userAgent = null;
        self::$userAgentOverridden = false;
    }

    /**
     * The user agent for the request about to be sent.
     *
     * Precedence: an explicit useUserAgent() override, then the `user_agent`
     * config value, then a string derived from the application itself.
     */
    public static function userAgent(): ?string
    {
        if (self::$userAgentOverridden) {
            return self::$userAgent instanceof Closure
                ? (self::$userAgent)()
                : self::$userAgent;
        }

        $configured = config('sentry-http-context.presets.user_agent');

        // An explicit `false` (SENTRY_HTTP_CONTEXT_USER_AGENT=false) switches the
        // header off; null simply means "not configured".
        if ($configured === false) {
            return null;
        }

        return $configured ?: self::defaultUserAgent();
    }

    private static function defaultUserAgent(): string
    {
        return sprintf(
            '%s (ENV: %s; URL: %s)',
            config('app.name'),
            App::environment(),
            config('app.url')
        );
    }
}
