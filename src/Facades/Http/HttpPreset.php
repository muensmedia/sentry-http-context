<?php

namespace Muensmedia\SentryHttpContext\Facades\Http;

use Illuminate\Support\Facades\Http as Http;

/**
 * @mixin HttpFactory
 */
class HttpPreset extends Http
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return HttpFactory::class;
    }
}
