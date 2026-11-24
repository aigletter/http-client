<?php

namespace Aigletter\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class HttpClient implements HttpClientInterface
{
    private UriInterface $baseUri;

    private string $method = 'GET';

    private string|array $path = [];

    private string|array $query = [];

    private string|array|StreamInterface $body = [];

    protected array $headers = [];

    public function __construct(
        private string $baseUrl,
        //private string $authToken,
        private ClientInterface $client,
        private UriFactoryInterface $uriFactory,
        private RequestFactoryInterface $requestFactory,
        protected StreamFactoryInterface $streamFactory,
    ) {
        $this->baseUri = $this->uriFactory->createUri($this->baseUrl);
    }

    public function get(UriInterface|string $url): ResponseInterface
    {
        $request = $this->makeRequest('GET', $url, null);

        return $this->sendRequest($request);
    }

    public function getJson(UriInterface|string $url): mixed
    {
        return json_decode($this->get($url)->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);
    }

    public function post(UriInterface|string $url, ?StreamInterface $body): ResponseInterface
    {
        $request = $this->makeRequest('POST', $url, $body);

        return $this->sendRequest($request);
    }

    public function put(UriInterface|string $url, ?StreamInterface $body): ResponseInterface
    {
        $request = $this->makeRequest('PUT', $url, $body);

        return $this->sendRequest($request);
    }

    public function options(UriInterface|string $url): ResponseInterface
    {
        $request = $this->makeRequest('OPTIONS', $url, null);

        return $this->sendRequest($request);
    }

    public function head(UriInterface|string $url): ResponseInterface
    {
        $request = $this->makeRequest('HEAD', $url, null);

        return $this->sendRequest($request);
    }

    public function connect(UriInterface|string $url): ResponseInterface
    {
        $request = $this->makeRequest('CONNECT', $url, null);

        return $this->sendRequest($request);
    }

    public function trace(UriInterface|string $url): ResponseInterface
    {
        $request = $this->makeRequest('TRACE', $url, null);

        return $this->sendRequest($request);
    }

    public function patch(UriInterface|string $url, ?StreamInterface $body): ResponseInterface
    {
        $request = $this->makeRequest('PATCH', $url, $body);

        return $this->sendRequest($request);
    }

    public function delete(UriInterface|string $url): ResponseInterface
    {
        $request = $this->makeRequest('DELETE', $url, null);

        return $this->sendRequest($request);
    }

    public function send(): ResponseInterface
    {
        $path = $this->makePath($this->path);
        $url = $this->makeUri($path, $this->query);

        if (in_array($this->method, ['GET', 'HEAD', 'DELETE', 'CONNECT', 'OPTIONS', 'TRACE'])) {
            return match ($this->method) {
                'GET' => $this->get($url),
                'HEAD' => $this->head($url),
                'DELETE' => $this->delete($url),
                'CONNECT' => $this->connect($url),
                'OPTIONS' => $this->options($url),
                'TRACE' => $this->trace($url),
            };
        }

        if (!array_key_exists('content-type', $this->headers)) {
            $this->headers['content-type'] = 'application/json';
        }
        $stream = $this->makeStream($this->body);

        return match ($this->method) {
            'POST' => $this->post($url, $stream),
            'PUT' => $this->put($url, $stream),
            'PATCH' => $this->patch($url, $stream),
            default => throw new \InvalidArgumentException('Invalid HTTP method'),
        };
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
            $params = $results;
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

    public function withToken(string $token): static
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function makeUri(string $path, array $params = []): UriInterface
    {

        $basePath = parse_url($this->baseUrl, PHP_URL_PATH) ?? '';

        $uri = $this->baseUri->withPath(
            rtrim($basePath, '/') . '/' . ltrim($path, '/')
        );

        if ($params) {
            $uri = $uri->withQuery(http_build_query($params));
        }

        return $uri;
    }

    private function makePath(array $params = []): string
    {
        $result = '';
        foreach ($params as $key => $value) {
            $current = is_int($key) ? $value : $key . '/' . $value;
            $result .= $current . '/';
        }
        return rtrim($result, '/');
    }

    private function makeRequest(string $method, UriInterface|string $url, ?StreamInterface $body): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        if ($this->headers) {
            foreach ($this->headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if ($body) {
            $request = $request->withBody($body);
        }

        return $request;
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

    private function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (!array_key_exists('accept', $this->headers)) {
            $request = $request->withHeader('accept', 'application/json');
        }

        $response = $this->client->sendRequest($request);

        return $response;
    }

}
