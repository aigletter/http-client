<?php

namespace Aigletter\HttpClient;

interface HttpClientFactoryInterface
{
    public function create(): HttpClientInterface;

    public function createCached(): CachedHttpClientInterface;
}