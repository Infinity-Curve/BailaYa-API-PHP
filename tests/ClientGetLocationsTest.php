<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\StudioLocation;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class ClientGetLocationsTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-abc';

    private function makeClient(MockHandler $mock, ?array &$history = null): Client
    {
        $stack = HandlerStack::create($mock);
        if (is_array($history)) {
            $stack->push(Middleware::history($history));
        }
        return new Client([
            'baseUrl' => self::BASE,
            'studioId' => self::STUDIO_ID,
            'guzzle' => ['handler' => $stack],
        ]);
    }

    public function testGetLocationsHitsPublicEndpointAndMapsDtos(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                [
                    'id' => 'loc-1',
                    'name' => 'Roma Nte. Studio',
                    'addressLine1' => 'Mérida 124',
                    'addressLine2' => 'Floor 2',
                    'city' => 'CDMX',
                    'state' => 'CDMX',
                    'postalCode' => '06700',
                    'country' => 'MX',
                    'latitude' => null,
                    'longitude' => null,
                    'isPrimary' => true,
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history);
        $locations = $client->getLocations();

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            self::BASE . '/public/studio/' . self::STUDIO_ID . '/locations',
            (string)$request->getUri()
        );
        // No auth header on the public endpoint.
        $this->assertSame('', $request->getHeaderLine('Authorization'));

        $this->assertCount(1, $locations);
        $this->assertInstanceOf(StudioLocation::class, $locations[0]);
        $this->assertTrue($locations[0]->isPrimary);
        $this->assertSame('Floor 2', $locations[0]->addressLine2);
    }
}
