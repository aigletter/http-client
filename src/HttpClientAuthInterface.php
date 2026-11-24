<?php

namespace Aigletter\HttpClient;

interface HttpClientAuthInterface
{
    public function getToken(): string;

    public function refreshToken(): ?string;
}