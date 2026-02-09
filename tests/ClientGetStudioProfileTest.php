<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\StudioProfile;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ClientGetStudioProfileTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-123';

    private function rawStudio(): array
    {
        return [
            'id' => self::STUDIO_ID,
            'name' => 'My Studio',
            'country' => 'MX',
            'address' => '123 Main St, Mexico City, CDMX 01000, Mexico',
            'unit' => 'Suite A',
            'businessHours' => '9–5',
            'description' => '{"en":"Hello","es":"Hola"}',
            'logo' => 'logo.png',
            'phone' => '555',
            'studioSize' => 100,
            'website' => 'https://x.com',
            'yearEstablished' => 2000,
            'timezone' => 'America/Mexico_City',
            'studioTypes' => [
                [
                    'name' => 'Salsa',
                    'description' => '{"en":"Feel the Caribbean","es":"Siente el Caribe"}',
                    'image' => 'salsa.png',
                ],
                [
                    'name' => 'Bachata',
                    'description' => '{"en":"Romantic dance","es":"Baile romántico"}',
                    'image' => 'bachata.png',
                ],
            ],
        ];
    }

    /** Helper: build client with mocked HTTP handler */
    private function makeClientWithMock(MockHandler $mock): Client
    {
        return new Client([
            'baseUrl' => self::BASE,
            'studioId' => self::STUDIO_ID,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);
    }

    /** Parses JSON fields and maps studioTypes correctly */
    public function testParsesJsonAndMapsStudioTypes(): void
    {
        $payload = $this->rawStudio();

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $profile = $client->getStudioProfile();

        $this->assertInstanceOf(StudioProfile::class, $profile);

        // Description parsed
        $this->assertSame(['en' => 'Hello', 'es' => 'Hola'], $profile->description);

        // StudioTypes parsed
        $this->assertCount(2, $profile->studioTypes);
        $this->assertSame('Salsa', $profile->studioTypes[0]->name);
        $this->assertSame(
            ['en' => 'Feel the Caribbean', 'es' => 'Siente el Caribe'],
            $profile->studioTypes[0]->description
        );
        $this->assertSame('salsa.png', $profile->studioTypes[0]->image);

        $this->assertSame('Bachata', $profile->studioTypes[1]->name);
        $this->assertSame(
            ['en' => 'Romantic dance', 'es' => 'Baile romántico'],
            $profile->studioTypes[1]->description
        );
        $this->assertSame('bachata.png', $profile->studioTypes[1]->image);
    }

    /**
     * Falls back to empty arrays on bad JSON for description/studioTypes descriptions.
     */
    public function testFallsBackToEmptyObjectOnBadJson(): void
    {
        $payload = $this->rawStudio();
        $payload['description'] = 'not-json';
        $payload['studioTypes'] = [
            ['name' => 'Foo', 'description' => 'bad-json', 'image' => 'x.png'],
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $profile = $client->getStudioProfile();

        $this->assertSame([], $profile->description);
        $this->assertCount(1, $profile->studioTypes);
        $this->assertSame('Foo', $profile->studioTypes[0]->name);
        $this->assertSame([], $profile->studioTypes[0]->description);
        $this->assertSame('x.png', $profile->studioTypes[0]->image);
    }

    /** Throws on HTTP error status */
    public function testThrowsOnHttpError(): void
    {
        $mock = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], json_encode((object)[])),
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*404/i');

        $client->getStudioProfile();
    }

    /** Throws if no studioId configured anywhere */
    public function testThrowsIfNoStudioIdAnywhere(): void
    {
        $mock = new MockHandler(); // not used; error thrown before HTTP
        $client = new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No studio ID provided/i');

        $client->getStudioProfile();
    }
}

