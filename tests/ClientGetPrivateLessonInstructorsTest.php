<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\PrivateLessonInstructor;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ClientGetPrivateLessonInstructorsTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-abc';

    private function rawInstructors(): array
    {
        return [
            [
                'id' => 'inst-1',
                'name' => 'Alice',
                'lastname' => 'Smith',
                'image' => 'alice.jpg',
                'bio' => '{"en":"Alice teaches salsa","es":"Alice enseña salsa"}',
                'availability' => [
                    ['id' => 'avail-1', 'dayOfWeek' => 1, 'startTime' => '09:00', 'endTime' => '17:00'],
                    ['id' => 'avail-2', 'dayOfWeek' => 3, 'startTime' => '10:00', 'endTime' => '18:00'],
                ],
                'pricing' => [
                    ['id' => 'price-1', 'durationMins' => 45, 'price' => 50.00, 'currency' => 'USD'],
                    ['id' => 'price-2', 'durationMins' => 60, 'price' => 65.00, 'currency' => 'USD'],
                ],
            ],
            [
                'id' => 'inst-2',
                'name' => 'Bob',
                'lastname' => 'Jones',
                'image' => null,
                'bio' => '{"en":"Bob teaches bachata"}',
                'availability' => [
                    ['id' => 'avail-3', 'dayOfWeek' => 5, 'startTime' => '14:00', 'endTime' => '20:00'],
                ],
                'pricing' => [
                    ['id' => 'price-3', 'durationMins' => 60, 'price' => 70.00, 'currency' => 'USD'],
                ],
            ],
        ];
    }

    private function makeClientWithMock(MockHandler $mock): Client
    {
        return new Client([
            'baseUrl' => self::BASE,
            'studioId' => self::STUDIO_ID,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);
    }

    public function testReturnsParsedInstructors(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($this->rawInstructors(), JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();

        $this->assertCount(2, $instructors);
        $this->assertInstanceOf(PrivateLessonInstructor::class, $instructors[0]);
    }

    public function testParsesAvailabilitySlots(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($this->rawInstructors(), JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();

        $this->assertCount(2, $instructors[0]->availability);
        $this->assertSame(1, $instructors[0]->availability[0]->dayOfWeek);
        $this->assertSame('09:00', $instructors[0]->availability[0]->startTime);
        $this->assertSame('17:00', $instructors[0]->availability[0]->endTime);
    }

    public function testParsesPricingEntries(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($this->rawInstructors(), JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();

        $this->assertCount(2, $instructors[0]->pricing);
        $this->assertSame(45, $instructors[0]->pricing[0]->durationMins);
        $this->assertSame(50.0, $instructors[0]->pricing[0]->price);
        $this->assertSame('USD', $instructors[0]->pricing[0]->currency);
    }

    public function testParsesBioFromJson(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($this->rawInstructors(), JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();

        $this->assertSame(['en' => 'Alice teaches salsa', 'es' => 'Alice enseña salsa'], $instructors[0]->bio);
    }

    public function testHandlesMalformedBioGracefully(): void
    {
        $payload = [[
            'id' => 'inst-x', 'name' => 'Dana', 'lastname' => null, 'image' => null,
            'bio' => 'not-json',
            'availability' => [], 'pricing' => [],
        ]];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();

        $this->assertSame([], $instructors[0]->bio);
    }

    public function testHandlesNullBioGracefully(): void
    {
        $payload = [[
            'id' => 'inst-y', 'name' => 'Eve', 'lastname' => null, 'image' => null,
            'bio' => null,
            'availability' => [], 'pricing' => [],
        ]];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();

        $this->assertSame([], $instructors[0]->bio);
    }

    public function testJsonSerializeRoundTrip(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($this->rawInstructors(), JSON_THROW_ON_ERROR)),
        ]);

        $instructors = $this->makeClientWithMock($mock)->getPrivateLessonInstructors();
        $serialized = json_decode(json_encode($instructors, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('inst-1', $serialized[0]['id']);
        $this->assertSame(['en' => 'Alice teaches salsa', 'es' => 'Alice enseña salsa'], $serialized[0]['bio']);
        $this->assertCount(2, $serialized[0]['availability']);
        $this->assertCount(2, $serialized[0]['pricing']);
        $this->assertSame(1, $serialized[0]['availability'][0]['dayOfWeek']);
        $this->assertSame(65.0, $serialized[0]['pricing'][1]['price']);
    }

    public function testThrowsOnApiError(): void
    {
        $mock = new MockHandler([
            new Response(404, ['Content-Type' => 'application/json'], json_encode(['message' => 'Studio not found'])),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*404/i');

        $this->makeClientWithMock($mock)->getPrivateLessonInstructors();
    }

    public function testThrowsIfNoStudioIdConfigured(): void
    {
        $mock = new MockHandler();
        $client = new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No studio ID provided/i');

        $client->getPrivateLessonInstructors();
    }
}
