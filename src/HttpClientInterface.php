<?php

namespace Aigletter\Autoparts\HttpClient;

use Psr\Http\Message\UriInterface;

interface HttpClientInterface
{
    public function get(UriInterface|string $url): mixed;
}