<?php

namespace Muensmedia\SentryHttpContext\Facades\Http;


use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\App;
use Sentry\Laravel\Features\HttpClientIntegration;

class HttpFactory extends Factory
{
    public string $description = '';

    public function __construct(?Dispatcher $dispatcher = null)
    {
        parent::__construct($dispatcher);
        $this->globalRequestMiddleware([
            app(HttpClientIntegration::class),
            'attachTracingHeadersToRequest',
        ]);
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Create a new pending request instance with default settings.
     *
     * @return PimpedPendingRequest
     */
    #[\Override]
    protected function newPendingRequest(): PimpedPendingRequest
    {
        $pendingRequest = (new PimpedPendingRequest($this, $this->globalMiddleware))
            ->withOptions(value($this->globalOptions));

        // add presets for default settings
        return $pendingRequest
            ->withUserAgent(self::buildUserAgent())
            ->asJson()
            ->acceptJson()
            ->timeout(60);
    }

    /**
     * Get the user agent string for this application.
     * @return string
     */
    private static function buildUserAgent(): string
    {
        return sprintf('%s (ENV: %s; URL: %s)',
            config('app.name'),
            App::environment(),
            config('app.url')
        );
    }
}
