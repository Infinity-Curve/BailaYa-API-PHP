<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\StudioClass;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class ClientGetClassesByTypeTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-classes';

    /** Raw payload parallel to your TS test */
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
                'name' => 'Salsa',
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

    /** Helper: client with mocked HTTP + optional history capture */
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

    /** Verifies mapping + DateTime conversion for filtered classes */
    public function testParsesAndMapsTypeSpecificClassesCorrectly(): void
    {
        $payload = $this->rawClasses();

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $classes = $client->getClassesByType('salsa');

        $this->assertCount(2, $classes);
        $this->assertInstanceOf(StudioClass::class, $classes[0]);
        $this->assertSame('Salsa', $classes[0]->name);
        $this->assertInstanceOf(DateTimeImmutable::class, $classes[1]->date);
        $this->assertNull($classes[1]->instructor);
    }

    /** Throws if no studio ID configured */
    public function testThrowsIfNoStudioIdAvailable(): void
    {
        $mock = new MockHandler();

        $client = new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No studio ID provided/i');

        $client->getClassesByType('Salsa');
    }

    /** Throws if typeName is empty */
    public function testThrowsIfTypeNameNotPassed(): void
    {
        $mock = new MockHandler([
            // Won’t be used because we throw before HTTP
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/getClassesByType requires a typeName argument/i');

        $client->getClassesByType('');
    }

    /** Ensures ?from=YYYY-MM-DD is included when provided */
    public function testIncludesFromQueryParameterInApiCall(): void
    {
        $payload = $this->rawClasses();
        $history = [];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock, $history);

        $from = new DateTimeImmutable('2025-08-01', new DateTimeZone('UTC'));
        $client->getClassesByType('salsa', $from);

        $this->assertNotEmpty($history, 'Expected at least one HTTP call.');
        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];

        $this->assertSame(
            self::BASE . '/studio/' . self::STUDIO_ID . '/classes/salsa?from=2025-08-01',
            (string)$request->getUri()
        );
    }

    /** Throws on non-2xx response */
    public function testThrowsOnApiErrorResponse(): void
    {
        $mock = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], json_encode((object)[])),
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*404/i');

        $client->getClassesByType('salsa');
    }
}
