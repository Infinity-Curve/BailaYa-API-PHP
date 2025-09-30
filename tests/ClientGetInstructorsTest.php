<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\Instructor;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ClientGetInstructorsTest extends TestCase
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
                'bio' => '{"en":"Alice is great","de":"Alice ist toll"}',
            ],
            [
                'id' => 'inst-2',
                'name' => 'Bob',
                'lastname' => 'Jones',
                'image' => 'bob.jpg',
                'bio' => '{"en":"Bob teaches tango"}',
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

    /** Verifies that all instructor bios are parsed from JSON */
    public function testParsesAllInstructorBiosCorrectly(): void
    {
        $payload = $this->rawInstructors();

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $instructors = $client->getInstructors();

        $this->assertCount(2, $instructors);
        $this->assertInstanceOf(Instructor::class, $instructors[0]);

        // Compare via JsonSerializable output for deep equality
        $this->assertSame(
            json_encode([
                [
                    'id' => 'inst-1',
                    'name' => 'Alice',
                    'lastname' => 'Smith',
                    'image' => 'alice.jpg',
                    'bio' => ['en' => 'Alice is great', 'de' => 'Alice ist toll'],
                ],
                [
                    'id' => 'inst-2',
                    'name' => 'Bob',
                    'lastname' => 'Jones',
                    'image' => 'bob.jpg',
                    'bio' => ['en' => 'Bob teaches tango'],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode($instructors, JSON_THROW_ON_ERROR)
        );
    }

    /** Handles malformed JSON bios gracefully (empty map) */
    public function testHandlesMalformedJsonBiosGracefully(): void
    {
        $payload = [
            [
                'id' => 'inst-3',
                'name' => 'Chris',
                'lastname' => 'Invalid',
                'image' => 'x.jpg',
                'bio' => 'not-json',
            ],
        ];

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $instructors = $client->getInstructors();

        $this->assertCount(1, $instructors);
        $this->assertSame('Chris', $instructors[0]->name);
        $this->assertSame([], $instructors[0]->bio);
    }

    /** Throws on API error response */
    public function testThrowsOnApiErrorResponse(): void
    {
        $mock = new MockHandler([
            new Response(403, ['Content-Type' => 'application/json'], json_encode((object)[])),
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*403/i');

        $client->getInstructors();
    }

    /** Throws if no studio ID configured */
    public function testThrowsIfNoStudioIdConfigured(): void
    {
        $mock = new MockHandler(); // not used; we error before HTTP
        $client = new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No studio ID provided/i');

        $client->getInstructors();
    }
}
