<?php

namespace Muensmedia\SentryHttpContext\Facades\Http;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Sentry\Breadcrumb;
use function Sentry\addBreadcrumb;

class PimpedPendingRequest extends PendingRequest
{
    /**
     * Send the request to the given URL.
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return Response
     *
     * @throws Exception
     * @throws ConnectionException
     */
    public function send(string $method, string $url, array $options = []): Response
    {
        $url = $this->expandUrlParameters($url);
        $options = $this->parseHttpOptions($options);

        addBreadcrumb(
            'HTTP Request',
            $this->factory->description,
            [
                'method' => $method,
                'url' => $url,
                'options' => $options,
                'data' => $this->parseRequestData($method, $url, $options),
            ],
            type: Breadcrumb::TYPE_HTTP,
        );

        $response = parent::send($method, $url, $options);

        addBreadcrumb(
            'HTTP Response',
            $this->factory->description,
            [
                'method' => $method,
                'url' => $url,
                'response' => $response?->json() ?? $response?->body(),
            ],
            type: Breadcrumb::TYPE_HTTP,
        );
        return $response;
    }
}
