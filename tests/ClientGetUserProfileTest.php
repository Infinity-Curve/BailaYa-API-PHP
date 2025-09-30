<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\Dto\UserProfile;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

final class ClientGetUserProfileTest extends TestCase
{
    private const BASE = 'https://test.api';

    /** Raw payload parallel to your TS test */
    private function rawUser(): array
    {
        return [
            'id' => 'user-456',
            'name' => 'Jane',
            'lastname' => 'Doe',
            'image' => 'jane.png',
            'bio' => '{"en":"Bio EN","fr":"Bio FR"}',
            'occupation' => 'Teacher',
            'yearsOfExperience' => 5,
            'danceSpecialities' => ['Salsa','Tango'],
        ];
    }

    /** Helper: build client with mocked HTTP handler */
    private function makeClientWithMock(MockHandler $mock): Client
    {
        return new Client([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => HandlerStack::create($mock)],
        ]);
    }

    /** Parses JSON bio correctly */
    public function testParsesJsonBioCorrectly(): void
    {
        $payload = $this->rawUser();

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $profile = $client->getUserProfile($payload['id']);

        $this->assertInstanceOf(UserProfile::class, $profile);
        $this->assertSame(['en' => 'Bio EN', 'fr' => 'Bio FR'], $profile->bio);
    }

    /** Falls back to empty map on bad bio JSON */
    public function testFallsBackOnBadBioJson(): void
    {
        $payload = $this->rawUser();
        $payload['bio'] = 'not-json';

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);

        $client = $this->makeClientWithMock($mock);
        $profile = $client->getUserProfile($payload['id']);

        $this->assertSame([], $profile->bio);
    }

    /** Throws on HTTP error */
    public function testThrowsOnHttpError(): void
    {
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], json_encode((object)[])),
        ]);

        $client = $this->makeClientWithMock($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Request failed:\s*500/i');

        $client->getUserProfile('user-456');
    }

    /** Throws if called without userId */
    public function testThrowsIfCalledWithoutUserId(): void
    {
        $mock = new MockHandler(); // not used; we throw before HTTP
        $client = $this->makeClientWithMock($mock);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/getUserProfile requires a userId argument/i');

        $client->getUserProfile('');
    }
}
