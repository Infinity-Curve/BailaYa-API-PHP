<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\StudioClass;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class ClientGetClassesTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-classes';

    private function rawClasses(): array
    {
        return [
            [
                'id' => 'class-1',
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
                'instructor' => [
                    'id' => 'instructor-1',
                    'name' => 'Alice',
                    'lastname' => 'Smith',
                    'image' => 'alice.jpg',
                ],
            ],
            [
                'id' => 'class-2',
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
                'instructor' => null,
            ],
        ];
    }

    /** Helper to build a Client with a mocked handler and optional history capture */
    private function makeClientWithMock(MockHandler $mock, ?array &$history = null): Client
    {
        $stack = HandlerStack::create($mock);
        if (is_array($history)) {
            $historyMiddleware = Middleware::history($history);
            $stack->push($historyMiddleware);
        }

        return new Client([
            'baseUrl' => self::BASE,
            'studioId' => self::STUDIO_ID,
            'guzzle' => [
                'handler' => $stack,
            ],
        ]);
    }

    /** Verifies mapping + DateTime conversion */
    public function testParsesAndMapsStudioClassDatesIntoDateObjects(): void
    {
        $payload = $this->rawClasses();

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $classes = $client->getClasses();

        $this->assertCount(2, $classes);
        $this->assertInstanceOf(StudioClass::class, $classes[0]);
        $this->assertSame('Salsa', $classes[0]->name);
        // 'YYYY-MM-DD' -> UTC midnight
        $this->assertSame('2025-08-01', $classes[0]->date->format('Y-m-d'));
        $this->assertSame('+00:00', $classes[0]->date->format('P'));

        // ISO string preserved as instant
        $this->assertSame('Bachata', $classes[1]->name);
        $this->assertSame('2025-08-02T19:00:00+00:00', $classes[1]->date->format('c'));

        // null instructor maps to null
        $this->assertNull($classes[1]->instructor);
    }

    /** Confirms error thrown on non-2xx status */
    public function testThrowsOnApiErrorResponse(): void
    {
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], json_encode((object)[])),
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*500/i');

        $client->getClasses();
    }

    /** Ensures failure when no studioId is configured */
    public function testThrowsIfNoStudioIdConfigured(): void
    {
        $mock = new MockHandler(); // won’t be used; error occurs before HTTP

        // Build a client WITHOUT studioId (override option omitted)
        $stack = HandlerStack::create($mock);
        $client = new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => $stack],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No studio ID provided/i');

        $client->getClasses();
    }

    /** Ensures ?from=YYYY-MM-DD is appended when provided */
    public function testIncludesFromQueryParameterInApiCall(): void
    {
        $payload = $this->rawClasses();
        $history = [];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock, $history);

        $from = new DateTimeImmutable('2025-08-01', new DateTimeZone('UTC'));
        $client->getClasses($from);

        $this->assertNotEmpty($history, 'Expected at least one HTTP call.');
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];

        $this->assertSame(
            self::BASE . '/studio/' . self::STUDIO_ID . '/classes?from=2025-08-01',
            (string)$request->getUri()
        );
    }
}
