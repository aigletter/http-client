<?php

namespace Tests\Unit;

use Aigletter\HttpClient\HttpClient;
use Aigletter\HttpClient\CachedHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;

class HttpClientTest extends TestCase
{
    #[DataProvider('supportedMethods')]
    public function testSendSupportsHttpMethods(
        string $method,
        string|array|null $body,
        ?string $expectedBody,
    ): void {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::once())
            ->method('withPath')
            ->with('/api/examples/id/1')
            ->willReturnSelf();

        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->expects(self::once())
            ->method('createUri')
            ->with('http://example.com/api')
            ->willReturn($uri);

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        if ($expectedBody !== null) {
            $stream = $this->createMock(StreamInterface::class);
            $streamFactory = $this->createMock(StreamFactoryInterface::class);
            $streamFactory->expects(self::once())
                ->method('createStream')
                ->with($expectedBody)
                ->willReturn($stream);
            $request->expects(self::once())->method('withBody')->with($stream)->willReturnSelf();
        } else {
            $streamFactory = $this->createMock(StreamFactoryInterface::class);
            $streamFactory->expects(self::never())->method('createStream');
            $request->expects(self::never())->method('withBody');
        }

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with($method, $uri)
            ->willReturn($request);

        $responseBody = $this->createMock(StreamInterface::class);
        $responseBody->method('getContents')->willReturn('{}');
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($responseBody);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('sendRequest')
            ->with($request)
            ->willReturn($response);

        $httpClient = new HttpClient(
            'http://example.com/api',
            $client,
            $uriFactory,
            $requestFactory,
            $streamFactory,
        );

        $httpClient = $httpClient
            ->withMethod($method)
            ->withPath(['examples', 'id' => 1]);
        if ($body !== null) {
            $httpClient = $httpClient->withBody($body);
        }

        self::assertInstanceOf(ResponseInterface::class, $httpClient->send());
    }

    public static function supportedMethods(): iterable
    {
        yield 'GET without body' => ['GET', null, null];
        yield 'HEAD without body' => ['HEAD', null, null];
        yield 'POST with JSON body' => ['POST', ['name' => 'updated'], '{"name":"updated"}'];
        yield 'PUT with JSON body' => ['PUT', ['name' => 'updated'], '{"name":"updated"}'];
        yield 'DELETE without body' => ['DELETE', null, null];
        yield 'CONNECT without body' => ['CONNECT', null, null];
        yield 'OPTIONS without body' => ['OPTIONS', null, null];
        yield 'TRACE without body' => ['TRACE', null, null];
        yield 'PATCH with JSON body' => ['PATCH', ['name' => 'updated'], '{"name":"updated"}'];
    }

    public function testPathAndStringQueryBuilderConstructRequestUri(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::once())
            ->method('withPath')
            ->with('/api/products/id/42')
            ->willReturnSelf();
        $uri->expects(self::once())
            ->method('withQuery')
            ->with('include=details&page=2')
            ->willReturnSelf();

        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn($uri);

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', $uri)
            ->willReturn($request);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willReturn($this->successfulResponse());

        $httpClient = $this->createHttpClient(
            'http://example.com/api',
            $client,
            $uriFactory,
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class),
        );

        $httpClient
            ->withPath(['products', 'id' => 42])
            ->withQuery('include=details&page=2')
            ->send();
    }

    public function testGetJsonDecodesTheResponseBody(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($this->successfulResponse());
        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn($this->createMock(UriInterface::class));

        $httpClient = $this->createHttpClient(
            'http://example.com',
            $client,
            $uriFactory,
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class),
        );

        self::assertEquals((object) [], $httpClient->getJson('http://example.com/items'));
    }

    public function testHeaderAndTokenBuildersApplyHeaders(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('withPath')->willReturnSelf();
        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn($uri);

        $request = $this->createMock(RequestInterface::class);
        $headers = [];
        $request->method('withHeader')
            ->willReturnCallback(static function (string $name, string $value) use (&$headers, $request) {
                $headers[] = [$name, $value];

                return $request;
            });
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $client = $this->createMock(ClientInterface::class);
        $client->method('sendRequest')->willReturn($this->successfulResponse());

        $httpClient = $this->createHttpClient(
            'http://example.com/api',
            $client,
            $uriFactory,
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class),
        );

        $httpClient
            ->withHeader('X-Trace-Id', 'trace-123')
            ->withAuth('secret-token')
            ->send();

        self::assertSame([
            ['x-trace-id', 'trace-123'],
            ['authorization', 'Bearer secret-token'],
            ['accept', 'application/json'],
        ], $headers);
    }

    public function testRefreshesTokenAfterUnauthorizedResponseAndKeepsIt(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $authorization = null;
        $request->method('withHeader')
            ->willReturnCallback(static function (string $name, string $value) use (&$authorization, $request) {
                if (strtolower($name) === 'authorization') {
                    $authorization = $value;
                }

                return $request;
            });

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $sentAuthorizationHeaders = [];
        $statuses = [401, 200, 200];
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::exactly(3))
            ->method('sendRequest')
            ->willReturnCallback(function () use (&$sentAuthorizationHeaders, &$statuses, &$authorization) {
                $sentAuthorizationHeaders[] = $authorization;

                return $this->successfulResponse(array_shift($statuses));
            });

        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn($this->createMock(UriInterface::class));
        $httpClient = $this->createHttpClient(
            'http://example.com',
            $client,
            $uriFactory,
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class),
        )->withAuth('expired-token', static fn (): string => 'fresh-token');

        self::assertSame(200, $httpClient->get('http://example.com/items')->getStatusCode());
        self::assertSame(200, $httpClient->get('http://example.com/items')->getStatusCode());
        self::assertSame([
            'Bearer expired-token',
            'Bearer fresh-token',
            'Bearer fresh-token',
        ], $sentAuthorizationHeaders);
    }

    public function testRefreshesTokenWhenUnderlyingClientThrowsUnauthorizedException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $authorization = null;
        $request->method('withHeader')
            ->willReturnCallback(static function (string $name, string $value) use (&$authorization, $request) {
                if (strtolower($name) === 'authorization') {
                    $authorization = $value;
                }

                return $request;
            });

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->method('createRequest')->willReturn($request);

        $sendCount = 0;
        $sentAuthorizationHeaders = [];
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::exactly(2))
            ->method('sendRequest')
            ->willReturnCallback(function () use (&$sendCount, &$sentAuthorizationHeaders, &$authorization) {
                $sentAuthorizationHeaders[] = $authorization;
                if (++$sendCount === 1) {
                    throw new \Exception('Unauthorized', 401);
                }

                return $this->successfulResponse();
            });

        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn($this->createMock(UriInterface::class));
        $httpClient = $this->createHttpClient(
            'http://example.com',
            $client,
            $uriFactory,
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class),
        )->withAuth('expired-token', static fn (): string => 'fresh-token');

        self::assertSame(200, $httpClient->get('http://example.com/items')->getStatusCode());
        self::assertSame(['Bearer expired-token', 'Bearer fresh-token'], $sentAuthorizationHeaders);
    }

    public function testCachedClientStoresGetResponses(): void
    {
        $url = 'http://example.com/items';
        $cacheKey = hash('sha256', serialize(['url' => $url, 'headers' => []]));
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('has')->with($cacheKey)->willReturn(false);

        $request = $this->createMock(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())
            ->method('createRequest')
            ->with('GET', $url)
            ->willReturn($request);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willReturn($this->successfulResponse());

        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->method('createUri')->willReturn($this->createMock(UriInterface::class));

        $httpClient = new CachedHttpClient(
            'http://example.com',
            $client,
            $uriFactory,
            $requestFactory,
            $this->createMock(StreamFactoryInterface::class),
            $cache,
            $this->createMock(ResponseFactoryInterface::class),
        );

        $cache->expects(self::once())->method('set')->with($cacheKey, self::anything(), 60);

        $httpClient->withCacheTtl(60)->get($url);
    }

    private function createHttpClient(
        string $baseUrl,
        ClientInterface $client,
        UriFactoryInterface $uriFactory,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
    ): HttpClient {
        return new HttpClient($baseUrl, $client, $uriFactory, $requestFactory, $streamFactory);
    }

    private function successfulResponse(int $statusCode = 200): ResponseInterface
    {
        $body = $this->createMock(StreamInterface::class);
        $body->method('getContents')->willReturn('{}');
        $body->method('__toString')->willReturn('{}');
        $body->method('isSeekable')->willReturn(true);
        $body->method('tell')->willReturn(0);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}
