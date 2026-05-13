<?php

namespace Aigletter\Autoparts\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;

class HttpClient implements HttpClientInterface
{
    public function __construct(
        private string $baseUrl,
        private string $authToken,
        private ClientInterface $client,
        private UriFactoryInterface $uriFactory,
        private RequestFactoryInterface $requestFactory,
        private ?CacheInterface $cache = null,
    ) {}

    public function get(UriInterface|string $url): mixed
    {
        return $this->getWithCache($url, function () use ($url) {
            $request = $this->requestFactory->createRequest('GET', $url);

            $response = $this->client->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch data.' . $response->getBody()->getContents());
            }

            $content = $response->getBody()->getContents();

            return json_decode($content);
        });
    }

    public function makeUrl(string $path, array $params = []): UriInterface
    {

        $uri = $this->uriFactory->createUri()
            ->withScheme(parse_url($this->baseUrl, PHP_URL_SCHEME))
            ->withHost(parse_url($this->baseUrl, PHP_URL_HOST))
            ->withPath(rtrim(parse_url($this->baseUrl, PHP_URL_PATH)) . '/' . ltrim($path, '/'));

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

    private function getWithCache(UriInterface|string $url, callable $callback): mixed
    {
        $key = is_string($url) ? $url : $url->__toString();
        if ($this->cache->has($key)) {
            // TODO Uncomment
            return $this->cache->get($key);
        }

        $result = $callback($url);

        $this->cache->set($key, $result);

        return $result;
    }
}