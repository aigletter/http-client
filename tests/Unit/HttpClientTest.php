<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Aigletter\Autoparts\HttpClient\HttpClient;
use Psr\Http\Message\UriInterface;

class HttpClientTest extends TestCase
{
    public function testGetByParams()
    {
        $expectedUri = 'http://example.com/examples/id/1';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->willReturn($response);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('withPath')->willReturnSelf();
        $uri->method('withQuery')->willReturnSelf();
        $uri->expects(self::once())->method('withPath')->with('/examples/id/1');
        $uri->expects(self::once())->method('withQuery')->with('limit=100');

        $uriFactory = $this->createMock(UriFactoryInterface::class);
        $uriFactory->expects(self::once())->method('createUri')->willReturn($uri);

        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $requestFactory->expects(self::once())->method('createRequest');

        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $httpClient = new HttpClient(
            'http://example.com',
            'example',
            $client,
            $uriFactory,
            $requestFactory,
            $streamFactory,
        );

        $httpClient
            ->withPath([
                'examples',
                'id' => 1,
            ])
            ->withQuery([
                'limit' => 100
            ])
            ->send();
    }
}