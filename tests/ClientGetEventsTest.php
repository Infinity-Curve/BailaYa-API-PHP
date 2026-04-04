<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\StudioEvent;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class ClientGetEventsTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-events';

    private function rawEvents(): array
    {
        return [
            [
                'id' => 'event-1',
                'name' => 'Salsa',
                'dayOfWeek' => 'monday',
                'startTime' => '18:00',
                'endTime' => '19:00',
                'level' => 'Beginner',
                'room' => 'Room A',
                'date' => '2025-08-01',
                'price' => 20,
                'capacity' => 20,
                'allowPackages' => true,
                'description' => ['en' => 'Social dancing', 'es' => 'Baile social'],
                'host' => [
                    'id' => 'instructor-1',
                    'name' => 'Alice',
                    'lastname' => 'Smith',
                    'image' => 'alice.jpg',
                ],
            ],
            [
                'id' => 'event-2',
                'name' => 'Bachata',
                'dayOfWeek' => 'tuesday',
                'startTime' => '19:00',
                'endTime' => '20:00',
                'level' => 'Intermediate',
                'room' => 'Room B',
                'date' => '2025-08-02T19:00:00.000Z',
                'price' => 25,
                'capacity' => 15,
                'allowPackages' => false,
                'host' => null,
            ],
        ];
    }

    private function makeClientWithMock(MockHandler $mock, ?array &$history = null): Client
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

    /** Verifies mapping + DateTime conversion + null host + description field */
    public function testParsesAndMapsStudioEventDatesIntoDateObjects(): void
    {
        $payload = $this->rawEvents();
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $events = $client->getEvents();

        $this->assertCount(2, $events);
        $this->assertInstanceOf(StudioEvent::class, $events[0]);
        // 'YYYY-MM-DD' -> UTC midnight
        $this->assertSame('2025-08-01', $events[0]->date->format('Y-m-d'));
        $this->assertSame('+00:00', $events[0]->date->format('P'));
        // description array preserved
        $this->assertSame(['en' => 'Social dancing', 'es' => 'Baile social'], $events[0]->description);
        // nullable fields present
        $this->assertSame('Room A', $events[0]->room);
        $this->assertSame(20.0, (float)$events[0]->price);
        $this->assertSame(20, $events[0]->capacity);
        $this->assertTrue($events[0]->allowPackages);

        // ISO string preserved as instant
        $this->assertSame('2025-08-02T19:00:00+00:00', $events[1]->date->format('c'));
        $this->assertNull($events[1]->host);
        // description absent -> null
        $this->assertNull($events[1]->description);
    }

    /** nullable room/price/capacity/allowPackages/description default to null when absent */
    public function testNullableFieldsDefaultToNullWhenAbsent(): void
    {
        $payload = [
            [
                'id' => 'event-min',
                'name' => 'Kizomba Night',
                'dayOfWeek' => 'friday',
                'startTime' => '21:00',
                'endTime' => '23:00',
                'level' => 'All',
                'date' => '2025-09-05',
                'host' => null,
                // room, price, capacity, allowPackages, description intentionally absent
            ],
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $events = $client->getEvents();

        $this->assertCount(1, $events);
        $ev = $events[0];
        $this->assertNull($ev->room);
        $this->assertNull($ev->price);
        $this->assertNull($ev->capacity);
        $this->assertNull($ev->allowPackages);
        $this->assertNull($ev->description);
    }

    /** Confirms error thrown on non-2xx */
    public function testThrowsOnApiErrorResponse(): void
    {
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], json_encode((object)[])),
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*500/i');

        $client->getEvents();
    }

    /** Ensures failure when no studioId configured */
    public function testThrowsIfNoStudioIdConfigured(): void
    {
        $mock = new MockHandler(); // not used; fails before HTTP
        $client = new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No studio ID provided/i');

        $client->getEvents();
    }

    /** Ensures ?from=YYYY-MM-DD is appended when provided */
    public function testIncludesFromQueryParameterInApiCall(): void
    {
        $payload = $this->rawEvents();
        $history = [];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock, $history);

        $from = new DateTimeImmutable('2025-08-01', new DateTimeZone('UTC'));
        $client->getEvents($from);

        $this->assertNotEmpty($history);
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];

        $this->assertSame(
            self::BASE . '/studio/' . self::STUDIO_ID . '/events?from=2025-08-01T00%3A00%3A00.000Z',
            (string)$request->getUri()
        );
    }
}
