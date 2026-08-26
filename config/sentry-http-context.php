<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    |
    | Every outgoing request made through Laravel's HTTP client is recorded as a
    | pair of Sentry breadcrumbs: one for the request, one for the response (or
    | the connection failure). Bodies that cannot be decoded are truncated to
    | keep the payload small - Sentry drops events that grow too large.
    |
    */

    'breadcrumbs' => [
        'enabled' => env('SENTRY_HTTP_CONTEXT_BREADCRUMBS', true),
        // Hard cap for bodies that cannot be decoded, ellipsis included.
        'max_body_length' => env('SENTRY_HTTP_CONTEXT_MAX_BODY_LENGTH', 4096),

        // Request headers masked before the breadcrumb is handed to Sentry.
        // Matched case-insensitively.
        'redacted_headers' => [
            'authorization',
            'proxy-authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request presets
    |--------------------------------------------------------------------------
    |
    | Defaults applied to every pending request. They are defaults, not
    | overrides: anything the caller sets explicitly wins.
    |
    */

    'presets' => [

        'enabled' => env('SENTRY_HTTP_CONTEXT_PRESETS', true),

        /*
         * The user agent sent with every request.
         *
         * Leave it unset to derive one from the application:
         * "{app.name} (ENV: {environment}; URL: {app.url})". Set a string here
         * for a fixed one, or `false` to send none at all.
         *
         * Anything that needs to be computed at runtime belongs in a service
         * provider instead, where the application is booted:
         *
         *     SentryHttpContext::useUserAgent(fn () => 'Acme/'.config('app.version'));
         */
        'user_agent' => env('SENTRY_HTTP_CONTEXT_USER_AGENT'),

        'accept_json' => true,

        'timeout' => 60,

    ],

    /*
    |--------------------------------------------------------------------------
    | Sentry's own HTTP breadcrumbs
    |--------------------------------------------------------------------------
    |
    | sentry-laravel records its own (leaner) breadcrumb for each HTTP client
    | response. Leaving this on turns those off so requests do not show up twice
    | in the trail. Set it to false to keep both.
    |
    */

    'replace_sentry_breadcrumbs' => true,

];
