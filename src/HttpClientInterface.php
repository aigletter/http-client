<?php

namespace Aigletter\HttpClient;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

interface HttpClientInterface
{
    public function get(UriInterface|string $url): ResponseInterface;

    public function getJson(UriInterface|string $url): mixed;

    public function post(UriInterface|string $url, ?StreamInterface $body): ResponseInterface;

    public function put(UriInterface|string $url, ?StreamInterface $body): ResponseInterface;

    public function options(UriInterface|string $url): ResponseInterface;

    public function head(UriInterface|string $url): ResponseInterface;

    public function connect(UriInterface|string $url): ResponseInterface;

    public function trace(UriInterface|string $url): ResponseInterface;

    public function patch(UriInterface|string $url, ?StreamInterface $body): ResponseInterface;

    public function delete(UriInterface|string $url): ResponseInterface;

    public function withMethod(string $method): static;

    public function withPath(string|array $params): static;

    public function withQuery(string|array $params): static;

    public function withHeader(string $name, string $value): static;

    public function withAuth(HttpClientAuthInterface $auth): static;

    public function withBody(string|array|StreamInterface $body): static;

    public function send(): ResponseInterface;
}
