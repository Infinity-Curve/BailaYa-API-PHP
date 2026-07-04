<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\ManagementClass;
use BailaYa\Dto\ManagementStudent;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class ClientManagementTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const STUDIO_ID = 'studio-mgmt';
    private const API_KEY = 'bya_live_testkey';

    /**
     * Builds a Client with a mocked handler and optional history capture.
     *
     * @param array<string,mixed> $extraOptions
     */
    private function makeClient(MockHandler $mock, ?array &$history = null, array $extraOptions = []): Client
    {
        $stack = HandlerStack::create($mock);
        if (is_array($history)) {
            $stack->push(Middleware::history($history));
        }

        return new Client(array_merge([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => $stack],
        ], $extraOptions));
    }

    private function rawClass(string $id = 'class-1'): array
    {
        return [
            'id' => $id,
            'name' => 'Salsa L1',
            'description' => ['en' => 'Fun'],
            'discipline' => 'Salsa',
            'level' => 'Beginner',
            'room' => 'Room A',
            'roomId' => 'room-1',
            'location' => null,
            'date' => '2025-08-01T00:00:00.000Z',
            'dayOfWeek' => 'friday',
            'startTime' => '18:00',
            'endTime' => '19:00',
            'capacity' => 20,
            'price' => 20,
            'studioTypeId' => 'type-1',
            'teamMemberId' => 'tm-1',
            'classTemplateId' => null,
            'recurrenceId' => 'rec-1',
            'isException' => false,
            'allowPackages' => true,
            'requirePackage' => false,
            'isVirtual' => false,
            'virtualPlatform' => null,
            'virtualMeetingUrl' => null,
            'studioId' => self::STUDIO_ID,
            'createdAt' => '2025-07-01T00:00:00.000Z',
            'updatedAt' => '2025-07-02T00:00:00.000Z',
        ];
    }

    /** login POSTs to /v1/auth/token and stores the returned tokens. */
    public function testLoginPostsToAuthTokenEndpoint(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'accessToken' => 'access-123',
                'refreshToken' => 'refresh-456',
                'expiresInSeconds' => 900,
                'userId' => 'user-1',
                'studioId' => self::STUDIO_ID,
                'role' => 'owner',
                'session' => null,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history);
        $result = $client->login('owner@example.com', 'secret', 'phpunit');

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::BASE . '/v1/auth/token', (string)$request->getUri());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $sentBody = json_decode((string)$request->getBody(), true);
        $this->assertSame('owner@example.com', $sentBody['email']);
        $this->assertSame('secret', $sentBody['password']);
        $this->assertSame('phpunit', $sentBody['deviceName']);

        $this->assertSame('access-123', $result['accessToken']);
        $this->assertSame('refresh-456', $result['refreshToken']);
    }

    /** createClass sends Bearer auth + JSON body and maps the returned list of DTOs. */
    public function testCreateClassSendsAuthAndBodyAndReturnsDtos(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(201, ['Content-Type' => 'application/json'], json_encode([
                'data' => [$this->rawClass('class-1'), $this->rawClass('class-2')],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history, [
            'accessToken' => 'user-token-abc',
        ]);

        $created = $client->createClass([
            'name' => 'Salsa L1',
            'discipline' => 'Salsa',
            'level' => 'Beginner',
            'startTime' => '18:00',
            'endTime' => '19:00',
            'teamMemberId' => 'tm-1',
            'date' => '2025-08-01',
        ]);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::BASE . '/v1/classes', (string)$request->getUri());
        $this->assertSame('Bearer user-token-abc', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $sentBody = json_decode((string)$request->getBody(), true);
        $this->assertSame('Salsa', $sentBody['discipline']);

        $this->assertCount(2, $created);
        $this->assertInstanceOf(ManagementClass::class, $created[0]);
        $this->assertSame('class-1', $created[0]->id);
        $this->assertTrue($created[0]->allowPackages);
    }

    /** listStudents sends the API-key auth header. */
    public function testListStudentsSendsApiKeyAuthHeader(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => [[
                    'id' => 'stu-1',
                    'name' => 'Jane',
                    'lastname' => 'Doe',
                    'email' => 'jane@example.com',
                    'phone' => null,
                    'level' => 'Beginner',
                    'status' => 'active',
                    'userId' => null,
                    'studioId' => self::STUDIO_ID,
                    'createdAt' => '2025-07-01T00:00:00.000Z',
                    'updatedAt' => '2025-07-01T00:00:00.000Z',
                ]],
                'pagination' => ['limit' => 50, 'offset' => 0, 'count' => 1],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history, ['apiKey' => self::API_KEY]);
        $students = $client->listStudents();

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(self::BASE . '/v1/students', (string)$request->getUri());
        $this->assertSame('Bearer ' . self::API_KEY, $request->getHeaderLine('Authorization'));

        $this->assertCount(1, $students);
        $this->assertInstanceOf(ManagementStudent::class, $students[0]);
        $this->assertSame('jane@example.com', $students[0]->email);
    }

    /** X-Studio-Id header is sent when studioId is configured. */
    public function testSendsStudioIdHeaderWhenConfigured(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => []], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history, [
            'apiKey' => self::API_KEY,
            'studioId' => self::STUDIO_ID,
        ]);
        $client->listClasses();

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame(self::STUDIO_ID, $request->getHeaderLine('X-Studio-Id'));
    }

    /** No X-Studio-Id header when studioId is not configured. */
    public function testOmitsStudioIdHeaderWhenNotConfigured(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => []], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history, ['apiKey' => self::API_KEY]);
        $client->listClasses();

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertFalse($request->hasHeader('X-Studio-Id'));
    }

    /** A non-2xx error envelope throws RuntimeException with the server message. */
    public function testThrowsErrorEnvelopeMessage(): void
    {
        $mock = new MockHandler([
            new Response(422, ['Content-Type' => 'application/json'], json_encode([
                'error' => ['code' => 'VALIDATION', 'message' => 'startTime is required'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history, ['apiKey' => self::API_KEY]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('startTime is required');

        $client->createClass(['name' => 'Bad']);
    }

    /** Authenticated calls without credentials throw a RuntimeException. */
    public function testThrowsWhenNoCredentialsConfigured(): void
    {
        $mock = new MockHandler(); // no HTTP expected – error occurs before the request
        $client = $this->makeClient($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No credentials configured/i');

        $client->listClasses();
    }

    /** deleteClass appends ?applyToSeries=true and returns the decoded data payload. */
    public function testDeleteClassAppliesSeriesQueryAndReturnsData(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'data' => ['deleted' => 3],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClient($mock, $history, ['apiKey' => self::API_KEY]);
        $result = $client->deleteClass('class-1', true);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertSame(self::BASE . '/v1/classes/class-1?applyToSeries=true', (string)$request->getUri());
        $this->assertSame(['deleted' => 3], $result);
    }
}
