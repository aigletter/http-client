<?php

namespace Aigletter\HttpClient;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

interface HttpClientInterface
{
    public function get(UriInterface|string $url): mixed;

    public function post(UriInterface|string $url, ?StreamInterface $body): mixed;

    public function withMethod(string $method): static;

    public function withPath(string|array $params): static;

    public function withQuery(string|array $params): static;

    public function withHeader(string $name, string $value): static;

    public function withBody(string|array $body): static;

    public function withoutCache(): static;

    public function send(): mixed;

    public function getByParams(array $pathParams = [], array $queryParams = []): mixed;

    public function postByParams(array $pathParams = [], array $queryParams = [], array $body = []): mixed;
}