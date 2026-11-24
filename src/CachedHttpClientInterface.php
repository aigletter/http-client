<?php

namespace Aigletter\HttpClient;

use DateInterval;

/** Marker interface for an HTTP client configured to cache GET responses. */
interface CachedHttpClientInterface extends HttpClientInterface
{
    public function withoutCache(): static;

    public function withCacheTtl(int|DateInterval $ttl): static;
}
