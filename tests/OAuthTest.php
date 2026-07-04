<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BailaYa\Client;
use BailaYa\OAuth;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Middleware;

final class OAuthTest extends TestCase
{
    private const BASE = 'https://test.api';
    private const ISSUER = 'https://test.api/oidc';
    private const CLIENT_ID = 'app-client';
    private const REDIRECT = 'https://app.example.com/callback';

    /**
     * @param array<int,mixed> $history
     */
    private function makeOAuth(MockHandler $mock, ?array &$history = null): OAuth
    {
        $stack = HandlerStack::create($mock);
        if (is_array($history)) {
            $stack->push(Middleware::history($history));
        }

        return new OAuth([
            'baseUrl' => self::BASE,
            'guzzle' => ['handler' => $stack],
        ]);
    }

    private static function jsonResponse(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** The issuer is derived from the base URL, trimming a trailing slash. */
    public function testIssuerDerivation(): void
    {
        $this->assertSame('https://www.bailaya.com/api/oidc', (new OAuth())->getIssuer());
        $this->assertSame(self::ISSUER, (new OAuth(['baseUrl' => 'https://test.api/']))->getIssuer());
    }

    /** The client exposes an OAuth helper scoped to its base URL. */
    public function testClientExposesOAuthHelper(): void
    {
        $client = new Client(['baseUrl' => self::BASE]);
        $this->assertInstanceOf(OAuth::class, $client->oauth());
        $this->assertSame(self::ISSUER, $client->oauth()->getIssuer());
    }

    /** createPkcePair yields a valid verifier and its S256 challenge. */
    public function testCreatePkcePairProducesValidS256Challenge(): void
    {
        $mock = new MockHandler();
        $oauth = $this->makeOAuth($mock);

        $pair = $oauth->createPkcePair();

        $this->assertGreaterThanOrEqual(43, strlen($pair['codeVerifier']));
        $this->assertLessThanOrEqual(128, strlen($pair['codeVerifier']));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $pair['codeVerifier']);

        // Independently recompute base64url(SHA-256(verifier)).
        $expected = rtrim(strtr(base64_encode(hash('sha256', $pair['codeVerifier'], true)), '+/', '-_'), '=');
        $this->assertSame($expected, $pair['codeChallenge']);
    }

    /** A requested verifier length within bounds is honoured. */
    public function testCreatePkcePairHonoursLength(): void
    {
        $oauth = $this->makeOAuth(new MockHandler());
        $pair = $oauth->createPkcePair(43);
        $this->assertSame(43, strlen($pair['codeVerifier']));
    }

    /** buildAuthorizeUrl sets the required query params including S256 PKCE. */
    public function testBuildAuthorizeUrl(): void
    {
        $oauth = $this->makeOAuth(new MockHandler());

        $url = $oauth->buildAuthorizeUrl([
            'clientId' => self::CLIENT_ID,
            'redirectUri' => self::REDIRECT,
            'scope' => 'openid profile email offline_access classes:read',
            'state' => 'xyz-state',
            'codeChallenge' => 'the-challenge',
            'resource' => 'https://test.api',
            'prompt' => 'consent',
        ]);

        $this->assertStringStartsWith(self::ISSUER . '/auth?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame(self::CLIENT_ID, $q['client_id']);
        $this->assertSame(self::REDIRECT, $q['redirect_uri']);
        $this->assertSame('openid profile email offline_access classes:read', $q['scope']);
        $this->assertSame('xyz-state', $q['state']);
        $this->assertSame('the-challenge', $q['code_challenge']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertSame('https://test.api', $q['resource']);
        $this->assertSame('consent', $q['prompt']);
    }

    /** Optional resource and prompt are omitted when not provided. */
    public function testBuildAuthorizeUrlOmitsOptionalParams(): void
    {
        $oauth = $this->makeOAuth(new MockHandler());
        $url = $oauth->buildAuthorizeUrl([
            'clientId' => self::CLIENT_ID,
            'redirectUri' => self::REDIRECT,
            'scope' => 'openid',
            'state' => 's',
            'codeChallenge' => 'c',
        ]);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertArrayNotHasKey('resource', $q);
        $this->assertArrayNotHasKey('prompt', $q);
    }

    /** exchangeCode POSTs the authorization_code grant and returns tokens. */
    public function testExchangeCodePostsGrantAndReturnsTokens(): void
    {
        $tokens = [
            'access_token' => 'at-1',
            'refresh_token' => 'rt-1',
            'id_token' => 'idt-1',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'openid profile',
        ];
        $history = [];
        $mock = new MockHandler([self::jsonResponse(200, $tokens)]);
        $oauth = $this->makeOAuth($mock, $history);

        $res = $oauth->exchangeCode([
            'clientId' => self::CLIENT_ID,
            'code' => 'auth-code',
            'codeVerifier' => 'verifier',
            'redirectUri' => self::REDIRECT,
        ]);

        $this->assertSame($tokens, $res);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::ISSUER . '/token', (string)$request->getUri());
        $this->assertStringStartsWith(
            'application/x-www-form-urlencoded',
            $request->getHeaderLine('Content-Type')
        );

        parse_str((string)$request->getBody(), $body);
        $this->assertSame('authorization_code', $body['grant_type']);
        $this->assertSame(self::CLIENT_ID, $body['client_id']);
        $this->assertSame('auth-code', $body['code']);
        $this->assertSame('verifier', $body['code_verifier']);
        $this->assertSame(self::REDIRECT, $body['redirect_uri']);
        $this->assertArrayNotHasKey('client_secret', $body);
    }

    /** A client secret is sent for confidential clients. */
    public function testExchangeCodeIncludesClientSecret(): void
    {
        $history = [];
        $mock = new MockHandler([self::jsonResponse(200, ['access_token' => 'x', 'token_type' => 'Bearer'])]);
        $oauth = $this->makeOAuth($mock, $history);

        $oauth->exchangeCode([
            'clientId' => self::CLIENT_ID,
            'code' => 'c',
            'codeVerifier' => 'v',
            'redirectUri' => self::REDIRECT,
            'clientSecret' => 'shh',
        ]);

        parse_str((string)$history[0]['request']->getBody(), $body);
        $this->assertSame('shh', $body['client_secret']);
    }

    /** A non-2xx token response throws with the error_description. */
    public function testExchangeCodeThrowsErrorDescription(): void
    {
        $mock = new MockHandler([
            self::jsonResponse(400, ['error' => 'invalid_grant', 'error_description' => 'Code already used']),
        ]);
        $oauth = $this->makeOAuth($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Code already used');

        $oauth->exchangeCode([
            'clientId' => self::CLIENT_ID,
            'code' => 'c',
            'codeVerifier' => 'v',
            'redirectUri' => self::REDIRECT,
        ]);
    }

    /** refreshToken POSTs the refresh_token grant. */
    public function testRefreshTokenPostsGrant(): void
    {
        $history = [];
        $mock = new MockHandler([self::jsonResponse(200, ['access_token' => 'new', 'token_type' => 'Bearer'])]);
        $oauth = $this->makeOAuth($mock, $history);

        $oauth->refreshToken(['clientId' => self::CLIENT_ID, 'refreshToken' => 'rt-old']);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame(self::ISSUER . '/token', (string)$request->getUri());
        parse_str((string)$request->getBody(), $body);
        $this->assertSame('refresh_token', $body['grant_type']);
        $this->assertSame(self::CLIENT_ID, $body['client_id']);
        $this->assertSame('rt-old', $body['refresh_token']);
    }

    /** startDeviceAuthorization POSTs to /device/auth and returns codes. */
    public function testStartDeviceAuthorization(): void
    {
        $deviceResp = [
            'device_code' => 'dev-code',
            'user_code' => 'WXYZ-1234',
            'verification_uri' => 'https://test.api/oidc/device',
            'verification_uri_complete' => 'https://test.api/oidc/device?user_code=WXYZ-1234',
            'expires_in' => 900,
            'interval' => 5,
        ];
        $history = [];
        $mock = new MockHandler([self::jsonResponse(200, $deviceResp)]);
        $oauth = $this->makeOAuth($mock, $history);

        $res = $oauth->startDeviceAuthorization([
            'clientId' => self::CLIENT_ID,
            'scope' => 'openid offline_access',
        ]);

        $this->assertSame($deviceResp, $res);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::ISSUER . '/device/auth', (string)$request->getUri());
        parse_str((string)$request->getBody(), $body);
        $this->assertSame(self::CLIENT_ID, $body['client_id']);
        $this->assertSame('openid offline_access', $body['scope']);
    }

    /** pollDeviceToken waits on authorization_pending, then resolves with tokens. */
    public function testPollDeviceTokenHandlesPendingThenSuccess(): void
    {
        $tokens = ['access_token' => 'dev-at', 'token_type' => 'Bearer', 'expires_in' => 3600];
        $history = [];
        $mock = new MockHandler([
            self::jsonResponse(400, ['error' => 'authorization_pending']),
            self::jsonResponse(200, $tokens),
        ]);
        $oauth = $this->makeOAuth($mock, $history);

        $res = $oauth->pollDeviceToken([
            'clientId' => self::CLIENT_ID,
            'deviceCode' => 'dev-code',
            'interval' => 0, // no real delay in tests
        ]);

        $this->assertSame($tokens, $res);
        $this->assertCount(2, $history);

        parse_str((string)$history[1]['request']->getBody(), $body);
        $this->assertSame('urn:ietf:params:oauth:grant-type:device_code', $body['grant_type']);
        $this->assertSame('dev-code', $body['device_code']);
        $this->assertSame(self::CLIENT_ID, $body['client_id']);
    }

    /** pollDeviceToken throws on a terminal device error. */
    public function testPollDeviceTokenThrowsOnTerminalError(): void
    {
        $mock = new MockHandler([
            self::jsonResponse(400, ['error' => 'access_denied', 'error_description' => 'User denied the request']),
        ]);
        $oauth = $this->makeOAuth($mock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User denied the request');

        $oauth->pollDeviceToken([
            'clientId' => self::CLIENT_ID,
            'deviceCode' => 'dc',
            'interval' => 0,
        ]);
    }

    /** getUserInfo GETs /me with a bearer token. */
    public function testGetUserInfo(): void
    {
        $claims = ['sub' => 'user-1', 'email' => 'a@b.com', 'name' => 'Ana'];
        $history = [];
        $mock = new MockHandler([self::jsonResponse(200, $claims)]);
        $oauth = $this->makeOAuth($mock, $history);

        $res = $oauth->getUserInfo('access-xyz');
        $this->assertSame($claims, $res);

        /** @var \Psr\Http\Message\RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(self::ISSUER . '/me', (string)$request->getUri());
        $this->assertSame('Bearer access-xyz', $request->getHeaderLine('Authorization'));
    }

    /** getDiscoveryDocument GETs the well-known configuration. */
    public function testGetDiscoveryDocument(): void
    {
        $history = [];
        $mock = new MockHandler([self::jsonResponse(200, ['issuer' => self::ISSUER])]);
        $oauth = $this->makeOAuth($mock, $history);

        $res = $oauth->getDiscoveryDocument();
        $this->assertSame(['issuer' => self::ISSUER], $res);
        $this->assertSame(
            self::ISSUER . '/.well-known/openid-configuration',
            (string)$history[0]['request']->getUri()
        );
    }
}
