<?php

namespace Aigletter\Autoparts\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;

class HttpClient implements HttpClientInterface
{
    private UriInterface $baseUri;

    private string $method = 'GET';

    private string|array $path = [];

    private string|array $query = [];

    private array $headers = [];

    private bool $withCache = true;

    private string|array|StreamInterface $body = [];

    public function __construct(
        private string $baseUrl,
        private string $authToken,
        private ClientInterface $client,
        private UriFactoryInterface $uriFactory,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private ?CacheInterface $cache = null,
    ) {
        $this->baseUri = $this->uriFactory->createUri($this->baseUrl);
    }

    public function get(UriInterface|string $url): mixed
    {
        return $this->getWithCache($url, function () use ($url) {
            $request = $this->requestFactory->createRequest('GET', $url);

            if ($this->headers) {
                foreach ($this->headers as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }
            }

            return $this->handleRequest($request);
        });
    }

    public function post(UriInterface|string $url, ?StreamInterface $body): mixed
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        if ($body) {
            $request = $request->withBody($body);
        }

        if ($this->headers) {
            foreach ($this->headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        return $this->handleRequest($request);
    }

    public function send(): mixed
    {
        if ($this->method === 'GET') {
            return $this->sendGet();
        }

        if ($this->method === 'POST') {
            return $this->sendPost();
        }

        throw new \InvalidArgumentException('Invalid HTTP method');
    }

    private function sendGet()
    {
        $path = $this->makePath($this->path);
        $url = $this->makeUri($path, $this->query);

        return $this->get($url);
    }

    private function sendPost()
    {
        $path = $this->makePath($this->path);
        $url = $this->makeUri($path, $this->query);

        if (!array_key_exists('content-type', $this->headers)) {
            $this->headers['content-type'] = 'application/json';
        }

        $stream = $this->makeStream($this->body);

        return $this->post($url, $stream);
    }

    private function makeStream(string|array|StreamInterface $content): StreamInterface
    {
        if ($content instanceof StreamInterface) {
            return $content;
        }

        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE);
        }

        return $this->streamFactory->createStream($content);
    }

    public function withMethod(string $method): static
    {
        $client = clone $this;

        $client->method = strtoupper($method);

        return $client;
    }

    public function withPath(string|array $params): static
    {
        $client = clone $this;

        if (is_string($params)) {
            $params = [$params];
        }
        $client->path = $params;

        return $client;
    }

    public function withQuery(string|array $params): static
    {
        $client = clone $this;

        if (is_string($params)) {
            $results = [];
            parse_str($params, $results);
        }
        $client->query = $params;

        return $client;
    }

    public function withBody(string|array|StreamInterface $body): static
    {
        $client = clone $this;

        $client->body = $body;

        return $client;
    }

    public function withHeader(string $name, string $value): static
    {
        $client = clone $this;

        $name = strtolower($name);
        $client->headers[$name] = $value;

        return $client;
    }

    public function withoutCache(): static
    {
        $client = clone $this;

        $client->withCache = false;

        return $client;
    }

    public function getByParams(array $pathParams = [], array $queryParams = []): mixed
    {
        $path = $this->makePath($pathParams);
        $url = $this->makeUri($path, $queryParams);

        return $this->get($url);
    }

    public function postByParams(array $pathParams = [], array $queryParams = [], array $body = []): mixed
    {
        $path = $this->makePath($pathParams);
        $url = $this->makeUri($path, $queryParams);

        $content = json_encode($body, JSON_UNESCAPED_UNICODE);
        $body = $this->streamFactory->createStream($content);

        return $this->post($url, $body);
    }

    public function makeUri(string $path, array $params = []): UriInterface
    {

        $uri = $this->baseUri->withPath(rtrim(parse_url($this->baseUrl, PHP_URL_PATH)) . '/' . ltrim($path, '/'));

        if ($params) {
            $uri = $uri->withQuery(http_build_query($params));
        }

        return $uri;
    }

    public function makePath(array $params = []): string
    {
        $result = '';
        foreach ($params as $key => $value) {
            $current = is_int($key) ? $value : $key . '/' . $value;
            $result .= $current . '/';
        }
        return rtrim($result, '/');
    }

    private function handleRequest(RequestInterface $request): mixed
    {
        $response = $this->client->sendRequest($request);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode > 299) {
            throw new \Exception('Failed to fetch data.' . $response->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        return json_decode($content);
    }

    private function getWithCache(UriInterface|string $url, callable $callback): mixed
    {
        $key = $this->getCacheKey($url);
        if ($this->withCache && isset($this->cache) && $this->cache->has($key)) {
            // TODO Uncomment
            return $this->cache->get($key);
        }

        $result = $callback($url);

        if (isset($this->cache)) {
            $this->cache->set($key, $result);
        }

        return $result;
    }

    private function getCacheKey(UriInterface|string $url): string
    {
        if (!is_string($url)) {
            $url = $url->__toString();
        }

        return md5($url);
    }
}