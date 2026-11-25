<?php

namespace Aigletter\HttpClient;

use Closure;

class HttpClientAuth implements HttpClientAuthInterface
{
    private ?Closure $refreshToken;

    public function __construct(
        private string $token,
        ?callable $refreshToken = null
    ) {
        $this->refreshToken = $refreshToken === null ? null : Closure::fromCallable($refreshToken);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function hasRefreshToken(): bool
    {
        return $this->refreshToken !== null;
    }

    public function refreshToken(): ?string
    {
        if ($this->refreshToken === null) {
            return null;
        }

        $token = ($this->refreshToken)();
        if (!is_string($token) || $token === '') {
            throw new \UnexpectedValueException('The token refresh callback must return a non-empty string.');
        }

        $this->token = $token;

        return $this->token;
    }
}
