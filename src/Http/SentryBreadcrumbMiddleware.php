<?php

namespace Muensmedia\SentryHttpContext\Http;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sentry\Breadcrumb;
use Throwable;

use function Sentry\addBreadcrumb;

/**
 * Guzzle middleware that turns every outgoing HTTP client request into a pair of
 * Sentry breadcrumbs.
 *
 * This sits on the Guzzle handler stack rather than on Laravel's client events
 * (RequestSending / ResponseReceived) for one reason: only the handler stack is
 * handed the request options, and that is where the optional description set via
 * `Http::describe()` travels. The events carry no options at all.
 */
class SentryBreadcrumbMiddleware
{
    /**
     * The request option carrying the caller's description, if any.
     */
    public const DESCRIPTION_OPTION = 'sentry_description';

    /**
     * @param  string[]  $redactedHeaders  matched case-insensitively
     */
    public function __construct(
        private readonly int $maxBodyLength = 4096,
        private readonly array $redactedHeaders = [],
    ) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            $description = $options[self::DESCRIPTION_OPTION] ?? null;

            $this->recordRequest($request, $description);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($request, $description) {
                    $this->recordResponse($request, $response, $description);

                    return $response;
                },
                function ($reason) use ($request, $description) {
                    $this->recordFailure($request, $reason, $description);

                    return Create::rejectionFor($reason);
                }
            );
        };
    }

    private function recordRequest(RequestInterface $request, ?string $description): void
    {
        $wrapped = new ClientRequest($request);

        addBreadcrumb(
            'HTTP Request',
            $description,
            [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'headers' => $this->headers($request->getHeaders()),
                'data' => $this->payload($wrapped, $request->getBody()),
            ],
            type: Breadcrumb::TYPE_HTTP,
        );
    }

    private function recordResponse(RequestInterface $request, ResponseInterface $response, ?string $description): void
    {
        $body = (new ClientResponse($response))->json()
            ?? $this->truncate($this->read($response->getBody()));

        // Reading the body seeks the stream; the caller reads it after us, so it
        // has to be left rewound.
        $this->rewind($response->getBody());

        addBreadcrumb(
            'HTTP Response',
            $description,
            [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'status' => $response->getStatusCode(),
                'response' => $body,
            ],
            level: $response->getStatusCode() >= 400 ? Breadcrumb::LEVEL_WARNING : Breadcrumb::LEVEL_INFO,
            type: Breadcrumb::TYPE_HTTP,
        );
    }

    private function recordFailure(RequestInterface $request, mixed $reason, ?string $description): void
    {
        addBreadcrumb(
            'HTTP Failure',
            $description,
            [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri(),
                'reason' => $reason instanceof Throwable ? $reason->getMessage() : (string) $reason,
            ],
            level: Breadcrumb::LEVEL_ERROR,
            type: Breadcrumb::TYPE_HTTP,
        );
    }

    /**
     * The request payload as an array where it can be decoded, as a truncated
     * string otherwise.
     *
     * @return array<array-key, mixed>|string
     */
    private function payload(ClientRequest $request, StreamInterface $body): array|string
    {
        $data = $request->data();

        // Reading the body seeks the stream; the request is sent after us, so it
        // has to be left rewound.
        $this->rewind($body);

        if (is_array($data) && $data !== []) {
            return $data;
        }

        // Not a shape Laravel decodes (or no body at all).
        return $this->truncate($this->read($body));
    }

    private function read(StreamInterface $stream): string
    {
        if (! $stream->isSeekable()) {
            return '';
        }

        $stream->rewind();
        $contents = $stream->getContents();
        $stream->rewind();

        return $contents;
    }

    private function rewind(StreamInterface $stream): void
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
    }

    /**
     * Cap the body at `maxBodyLength` characters, ellipsis included - Str::limit()
     * appends its ellipsis after the cut, which would overshoot the limit.
     */
    private function truncate(string $body): string
    {
        $ellipsis = '...';

        if ($this->maxBodyLength <= strlen($ellipsis)) {
            return '';
        }

        return Str::limit($body, $this->maxBodyLength - strlen($ellipsis), $ellipsis);
    }

    /**
     * Flatten the PSR-7 header map, masking anything that carries a credential -
     * breadcrumbs are shipped to Sentry verbatim.
     *
     * @param  array<string, string[]>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        $redacted = array_map('strtolower', $this->redactedHeaders);

        $result = [];

        foreach ($headers as $name => $values) {
            $result[$name] = in_array(strtolower($name), $redacted, true)
                ? '[redacted]'
                : implode(', ', $values);
        }

        return $result;
    }
}
