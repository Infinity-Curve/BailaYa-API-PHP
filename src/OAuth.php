<?php
declare(strict_types=1);

namespace BailaYa;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * OAuth 2.0 / OpenID Connect client helpers for "Sign in with BailaYa".
 *
 * All clients MUST use PKCE (S256). Public clients pass only the PKCE
 * `codeVerifier`; confidential clients additionally pass a `clientSecret`.
 *
 * The access tokens obtained here can be passed as the `accessToken` option of
 * {@see Client} to call the Management API (`/v1/*`); they are scope-limited,
 * and multi-studio users must also set `studioId` (sent as the `X-Studio-Id`
 * header).
 */
class OAuth
{
    private string $baseUrl;
    /** The OIDC issuer, e.g. `https://www.bailaya.com/api/oidc`. */
    private string $issuer;
    private HttpClient $http;

    /**
     * @param array{
     *     baseUrl?:string,
     *     guzzle?:array<string,mixed>
     * } $options
     */
    public function __construct(array $options = [])
    {
        $this->baseUrl = rtrim($options['baseUrl'] ?? 'https://www.bailaya.com/api', '/');
        $this->issuer = $this->baseUrl . '/oidc';
        $this->http = new HttpClient($options['guzzle'] ?? []);
    }

    /** Returns the OIDC issuer URL (`${baseUrl}/oidc`). */
    public function getIssuer(): string
    {
        return $this->issuer;
    }

    /**
     * Generates a PKCE pair. The verifier is a 43–128 character base64url random
     * string; the challenge is `base64url(SHA-256(verifier))`.
     *
     * @param int $length Desired verifier length (clamped to 43–128).
     * @return array{codeVerifier:string,codeChallenge:string}
     */
    public function createPkcePair(int $length = 64): array
    {
        $size = max(43, min(128, $length));
        // base64url of `$size` random bytes yields >= `$size` chars; trim to length.
        $codeVerifier = substr(self::base64Url(random_bytes($size)), 0, $size);
        $codeChallenge = self::base64Url(hash('sha256', $codeVerifier, true));

        return ['codeVerifier' => $codeVerifier, 'codeChallenge' => $codeChallenge];
    }

    /**
     * Builds the authorization endpoint URL to redirect the user to.
     *
     * @param array{
     *     clientId:string,
     *     redirectUri:string,
     *     scope:string,
     *     state:string,
     *     codeChallenge:string,
     *     resource?:string,
     *     prompt?:string
     * } $params
     */
    public function buildAuthorizeUrl(array $params): string
    {
        $query = [
            'response_type' => 'code',
            'client_id' => $params['clientId'],
            'redirect_uri' => $params['redirectUri'],
            'scope' => $params['scope'],
            'state' => $params['state'],
            'code_challenge' => $params['codeChallenge'],
            'code_challenge_method' => 'S256',
        ];
        if (!empty($params['resource'])) {
            $query['resource'] = $params['resource'];
        }
        if (!empty($params['prompt'])) {
            $query['prompt'] = $params['prompt'];
        }

        return $this->issuer . '/auth?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges an authorization code for tokens (`grant_type=authorization_code`).
     *
     * @param array{
     *     clientId:string,
     *     code:string,
     *     codeVerifier:string,
     *     redirectUri:string,
     *     clientSecret?:string
     * } $params
     * @return array<string,mixed> `{ access_token, refresh_token, id_token, token_type, expires_in, scope }`
     * @throws \RuntimeException built from the OAuth error response on non-2xx.
     */
    public function exchangeCode(array $params): array
    {
        $body = [
            'grant_type' => 'authorization_code',
            'client_id' => $params['clientId'],
            'code' => $params['code'],
            'code_verifier' => $params['codeVerifier'],
            'redirect_uri' => $params['redirectUri'],
        ];
        if (!empty($params['clientSecret'])) {
            $body['client_secret'] = $params['clientSecret'];
        }

        return $this->expectOk($this->send('POST', $this->issuer . '/token', ['form_params' => $body]));
    }

    /**
     * Exchanges a refresh token for a fresh set of tokens
     * (`grant_type=refresh_token`).
     *
     * @param array{clientId:string,refreshToken:string,clientSecret?:string} $params
     * @return array<string,mixed>
     * @throws \RuntimeException built from the OAuth error response on non-2xx.
     */
    public function refreshToken(array $params): array
    {
        $body = [
            'grant_type' => 'refresh_token',
            'client_id' => $params['clientId'],
            'refresh_token' => $params['refreshToken'],
        ];
        if (!empty($params['clientSecret'])) {
            $body['client_secret'] = $params['clientSecret'];
        }

        return $this->expectOk($this->send('POST', $this->issuer . '/token', ['form_params' => $body]));
    }

    /**
     * Starts the OAuth 2.0 device authorization grant (`/device/auth`).
     *
     * @param array{clientId:string,scope:string} $params
     * @return array<string,mixed> `{ device_code, user_code, verification_uri, verification_uri_complete, expires_in, interval }`
     * @throws \RuntimeException built from the OAuth error response on non-2xx.
     */
    public function startDeviceAuthorization(array $params): array
    {
        $body = [
            'client_id' => $params['clientId'],
            'scope' => $params['scope'],
        ];

        return $this->expectOk($this->send('POST', $this->issuer . '/device/auth', ['form_params' => $body]));
    }

    /**
     * Polls the token endpoint for the device grant until the user approves,
     * the codes expire, or `maxWaitMs` elapses. Blocks between polls.
     *
     * Handles the standard `authorization_pending` (keep waiting) and `slow_down`
     * (increase the interval) responses.
     *
     * @param array{
     *     clientId:string,
     *     deviceCode:string,
     *     interval?:int,
     *     clientSecret?:string,
     *     maxWaitMs?:int
     * } $params
     * @return array<string,mixed> The token response once issued.
     * @throws \RuntimeException on failure, denial, expiry, or timeout.
     */
    public function pollDeviceToken(array $params): array
    {
        $interval = $params['interval'] ?? 5;
        $maxWaitMs = $params['maxWaitMs'] ?? 300000;
        $deadline = microtime(true) + $maxWaitMs / 1000;

        $body = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $params['clientId'],
            'device_code' => $params['deviceCode'],
        ];
        if (!empty($params['clientSecret'])) {
            $body['client_secret'] = $params['clientSecret'];
        }

        while (true) {
            $this->sleep($interval);

            $result = $this->send('POST', $this->issuer . '/token', ['form_params' => $body]);
            if ($result['status'] >= 200 && $result['status'] < 300) {
                return $result['json'];
            }

            $error = $result['json']['error'] ?? null;
            if ($error === 'authorization_pending') {
                // keep waiting
            } elseif ($error === 'slow_down') {
                $interval += 5;
            } else {
                throw new \RuntimeException(
                    $this->extractOAuthError($result['json'], $result['status'], $result['reason'])
                );
            }

            if (microtime(true) > $deadline) {
                throw new \RuntimeException('Device authorization timed out');
            }
        }
    }

    /**
     * Fetches the end-user claims from the UserInfo endpoint (`/me`).
     *
     * @param string $accessToken A valid OIDC access token.
     * @return array<string,mixed> The UserInfo claims.
     * @throws \RuntimeException built from the OAuth error response on non-2xx.
     */
    public function getUserInfo(string $accessToken): array
    {
        return $this->expectOk($this->send('GET', $this->issuer . '/me', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]));
    }

    /**
     * Fetches the OpenID Connect discovery document
     * (`/.well-known/openid-configuration`).
     *
     * @return array<string,mixed>
     * @throws \RuntimeException built from the OAuth error response on non-2xx.
     */
    public function getDiscoveryDocument(): array
    {
        return $this->expectOk($this->send('GET', $this->issuer . '/.well-known/openid-configuration', []));
    }

    /**
     * Blocks for the given number of seconds. Overridable in tests to avoid
     * real delays.
     */
    protected function sleep(int|float $seconds): void
    {
        if ($seconds > 0) {
            usleep((int)round($seconds * 1_000_000));
        }
    }

    /**
     * Sends a request and returns the status, decoded JSON body, and reason
     * phrase without throwing on non-2xx.
     *
     * @param array<string,mixed> $options
     * @return array{status:int,json:array<string,mixed>,reason:string}
     */
    private function send(string $method, string $url, array $options): array
    {
        $options['http_errors'] = false;
        $options['headers'] = array_merge(['Accept' => 'application/json'], $options['headers'] ?? []);

        try {
            $res = $this->http->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('HTTP error: ' . $e->getMessage(), 0, $e);
        }

        $body = (string)$res->getBody();
        $json = $body === '' ? [] : json_decode($body, true);
        if (!is_array($json)) {
            $json = [];
        }

        return [
            'status' => $res->getStatusCode(),
            'json' => $json,
            'reason' => $res->getReasonPhrase(),
        ];
    }

    /**
     * Returns the decoded JSON of a successful response, or throws a
     * RuntimeException carrying the OAuth error description on non-2xx.
     *
     * @param array{status:int,json:array<string,mixed>,reason:string} $result
     * @return array<string,mixed>
     */
    private function expectOk(array $result): array
    {
        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new \RuntimeException(
                $this->extractOAuthError($result['json'], $result['status'], $result['reason'])
            );
        }
        return $result['json'];
    }

    /**
     * Extracts a human-readable message from an OAuth error body, preferring
     * `error_description`, then `error`, then a generic status message.
     *
     * @param array<string,mixed> $json
     */
    private function extractOAuthError(array $json, int $status, string $reason): string
    {
        if (isset($json['error_description']) && is_string($json['error_description']) && $json['error_description'] !== '') {
            return $json['error_description'];
        }
        if (isset($json['error']) && is_string($json['error']) && $json['error'] !== '') {
            return $json['error'];
        }
        return "Request failed: {$status} {$reason}";
    }

    /** Encodes a binary string as unpadded base64url. */
    private static function base64Url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}
