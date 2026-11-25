<?php

namespace Aigletter\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;

/** HttpClient variant that caches GET results. */
class CachedHttpClient extends HttpClient implements CachedHttpClientInterface
{
    private bool $cacheEnabled = true;

    private int|\DateInterval|null $ttl = null;

    public function __construct(
        string $baseUrl,
        ClientInterface $client,
        UriFactoryInterface $uriFactory,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        private ResponseFactoryInterface $responseFactory,
        private CacheInterface $cache,
    ) {
        parent::__construct($baseUrl, $client, $uriFactory, $requestFactory, $streamFactory);
    }

    public function get(UriInterface|string $url): ResponseInterface
    {
        if (!$this->cacheEnabled) {
            return parent::get($url);
        }

        $key = $this->cacheKey($url);
        if ($this->cache->has($key)) {
            return $this->restoreResponse($this->cache->get($key));
        }

        $response = parent::get($url);
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $snapshot = $this->snapshotResponse($response);
            if ($snapshot !== null) {
                $this->cache->set($key, $snapshot, $this->ttl);
            }
        }

        return $response;
    }

    public function withoutCache(): static
    {
        $client = clone $this;
        $client->cacheEnabled = false;

        return $client;
    }

    public function withCacheTtl(int|\DateInterval $ttl): static
    {
        $client = clone $this;
        $client->ttl = $ttl;

        return $client;
    }

    private function cacheKey(UriInterface|string $url): string
    {
        $headers = $this->headers;
        if (isset($this->auth)) {
            $headers['authorization'] = 'Bearer ' . $this->auth->getToken();
        }
        ksort($headers);

        return hash('sha256', serialize([
            'url' => (string) $url,
            'headers' => $headers,
        ]));
    }

    private function snapshotResponse(ResponseInterface $response): ?array
    {
        $body = $response->getBody();
        if (!$body->isSeekable()) {
            return null;
        }

        $position = null;
        try {
            $position = $body->tell();
            $body->rewind();
            $contents = $body->getContents();
            $body->seek($position);
        } catch (\Throwable) {
            if ($position !== null) {
                try {
                    $body->seek($position);
                } catch (\Throwable) {
                    // The response is still returned; an uncacheable stream is not stored.
                }
            }

            return null;
        }

        return [
            'status' => $response->getStatusCode(),
            'reason' => $response->getReasonPhrase(),
            'headers' => $response->getHeaders(),
            'body' => $contents,
        ];
    }

    private function restoreResponse(mixed $snapshot): ResponseInterface
    {
        if (!is_array($snapshot)
            || !isset($snapshot['status'], $snapshot['headers'], $snapshot['body'])
            || !is_int($snapshot['status'])
            || !is_array($snapshot['headers'])
            || !is_string($snapshot['body'])
        ) {
            throw new \UnexpectedValueException('Invalid cached HTTP response.');
        }

        $response = $this->responseFactory->createResponse($snapshot['status'], $snapshot['reason'] ?? '');
        foreach ($snapshot['headers'] as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response->withBody($this->streamFactory->createStream($snapshot['body']));
    }
}
