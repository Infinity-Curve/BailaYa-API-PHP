<?php
declare(strict_types=1);

namespace BailaYa;

use BailaYa\Dto\Instructor as InstructorDto;
use BailaYa\Dto\ManagementClass as ManagementClassDto;
use BailaYa\Dto\ManagementPackage as ManagementPackageDto;
use BailaYa\Dto\ManagementStudent as ManagementStudentDto;
use BailaYa\Dto\ManagementTeamMember as ManagementTeamMemberDto;
use BailaYa\Dto\PrivateLessonInstructor as PrivateLessonInstructorDto;
use BailaYa\Dto\PrivateLessonQuestion as PrivateLessonQuestionDto;
use BailaYa\Dto\StudioPackage as StudioPackageDto;
use BailaYa\Dto\StudioClass as StudioClassDto;
use BailaYa\Dto\StudioEvent as StudioEventDto;
use BailaYa\Dto\StudioProfile as StudioProfileDto;
use BailaYa\Dto\StudioLocation as StudioLocationDto;
use BailaYa\Dto\ManagementRoom as ManagementRoomDto;
use BailaYa\Dto\UserProfile as UserProfileDto;
use BailaYa\Support\Date;
use Dotenv\Dotenv;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use DateTimeImmutable;

final class Client
{
    private string $baseUrl;
    private ?string $studioId;
    private ?string $apiKey;
    private ?string $accessToken;
    private ?string $refreshToken;
    private HttpClient $http;
    private ?LoggerInterface $logger;
    /** @var array<string,mixed> */
    private array $guzzleOptions;
    private ?OAuth $oauth = null;

    /**
     * @param array{
     *     baseUrl?:string,
     *     studioId?:string,
     *     apiKey?:string,
     *     accessToken?:string,
     *     refreshToken?:string,
     *     guzzle?:array<string,mixed>
     * } $options
     */
    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        if (is_readable(getcwd().'/.env')) {
            $dotenv = Dotenv::createImmutable(getcwd());
            $dotenv->safeLoad();
        }

        $this->baseUrl = $options['baseUrl'] ?? 'https://www.bailaya.com/api';
        $this->studioId = $options['studioId'] ?? self::getEnv('BAILAYA_STUDIO_ID');
        // Management API credentials (authenticated `/v1` endpoints).
        $this->apiKey = $options['apiKey'] ?? self::getEnv('BAILAYA_API_KEY');
        $this->accessToken = $options['accessToken'] ?? self::getEnv('BAILAYA_API_TOKEN');
        $this->refreshToken = $options['refreshToken'] ?? null;
        $this->guzzleOptions = $options['guzzle'] ?? [];
        $this->http = new HttpClient($this->guzzleOptions);
        $this->logger = $logger;
    }

    /**
     * OAuth 2.0 / OpenID Connect helpers ("Sign in with BailaYa"), scoped to
     * this client's base URL (issuer `${baseUrl}/oidc`). Lazily created.
     */
    public function oauth(): OAuth
    {
        if ($this->oauth === null) {
            $this->oauth = new OAuth([
                'baseUrl' => $this->baseUrl,
                'guzzle' => $this->guzzleOptions,
            ]);
        }
        return $this->oauth;
    }

    private static function getEnv(string $name): ?string
    {
        $val = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
        return ($val === false || $val === '') ? null : $val;
    }

    private function requireStudioId(?string $overrideId): string
    {
        $id = $overrideId ?? $this->studioId;
        if (!$id) {
            throw new \RuntimeException(
                'No studio ID provided – pass it to the method or set it in the client options (studioId).'
            );
        }
        return $id;
    }

    /**
     * Builds the authentication headers for Management API requests.
     *
     * Prefers the configured API key, then the user access token. When a studio
     * ID is configured it is sent as `X-Studio-Id` (used to scope user-token
     * requests to a specific studio).
     *
     * @return array<string,string>
     * @throws \RuntimeException when no credentials are configured.
     */
    private function authHeaders(): array
    {
        $token = $this->apiKey ?? $this->accessToken;
        if (!$token) {
            throw new \RuntimeException(
                'No credentials configured – pass an apiKey or accessToken to the client options '
                . '(or set BAILAYA_API_KEY / BAILAYA_API_TOKEN), or call login() first.'
            );
        }

        $headers = ['Authorization' => 'Bearer ' . $token];
        if ($this->studioId) {
            $headers['X-Studio-Id'] = $this->studioId;
        }
        return $headers;
    }

    /**
     * Extracts a human-readable error message from a response body. Prefers the
     * Management API error envelope `{ "error": { "code", "message" } }`,
     * falling back to a generic `Request failed: <status> <reason>` message.
     */
    private function extractErrorMessage(string $body, int $status, string $reason): string
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)
                && isset($decoded['error']['message'])
                && is_string($decoded['error']['message'])
                && $decoded['error']['message'] !== ''
            ) {
                return $decoded['error']['message'];
            }
        } catch (\Throwable) {
            // Ignore parse failures and fall back to the generic message.
        }
        return "Request failed: {$status} {$reason}";
    }

    /**
     * @template T of array
     * @param string $url
     * @param bool $auth When true, attaches Management API auth headers.
     * @return array{status:int,data:mixed}
     */
    private function getJson(string $url, bool $auth = false): array
    {
        try {
            $headers = ['Accept' => 'application/json'];
            if ($auth) {
                $headers = array_merge($headers, $this->authHeaders());
            }

            $res = $this->http->get($url, [
                'headers' => $headers,
                'http_errors' => false,
            ]);

            $status = $res->getStatusCode();
            $body = (string)$res->getBody();

            if ($status < 200 || $status >= 300) {
                $this->logger?->error('API request failed', ['url' => $url, 'status' => $status, 'body' => $body]);
                throw new \RuntimeException($this->extractErrorMessage($body, $status, $res->getReasonPhrase()));
            }

            if ($status === 204 || $body === '') {
                return ['status' => $status, 'data' => null];
            }

            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            return ['status' => $status, 'data' => $json];
        } catch (GuzzleException $e) {
            $this->logger?->error('HTTP error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new \RuntimeException('HTTP error: ' . $e->getMessage(), 0, $e);
        } catch (\JsonException $e) {
            $this->logger?->error('JSON parse error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Invalid JSON response: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Central request helper for the authenticated Management API (`/v1`).
     * Mirrors {@see getJson()} but supports POST/PATCH/DELETE with a JSON body.
     *
     * @param string $method HTTP method (POST, PATCH, DELETE, …).
     * @param string $url Absolute request URL.
     * @param array<string,mixed>|null $body Optional JSON body.
     * @param bool $auth When true, attaches Management API auth headers.
     * @return array{status:int,data:mixed} `data` is null for a 204 response.
     */
    private function sendJson(string $method, string $url, ?array $body = null, bool $auth = true): array
    {
        try {
            $headers = ['Accept' => 'application/json'];
            if ($auth) {
                $headers = array_merge($headers, $this->authHeaders());
            }

            $options = ['http_errors' => false];
            if ($body !== null) {
                $headers['Content-Type'] = 'application/json';
                $options['body'] = json_encode($body, JSON_THROW_ON_ERROR);
            }
            $options['headers'] = $headers;

            $res = $this->http->request($method, $url, $options);

            $status = $res->getStatusCode();
            $resBody = (string)$res->getBody();

            if ($status < 200 || $status >= 300) {
                $this->logger?->error('API request failed', ['url' => $url, 'status' => $status, 'body' => $resBody]);
                throw new \RuntimeException($this->extractErrorMessage($resBody, $status, $res->getReasonPhrase()));
            }

            if ($status === 204 || $resBody === '') {
                return ['status' => $status, 'data' => null];
            }

            $json = json_decode($resBody, true, 512, JSON_THROW_ON_ERROR);
            return ['status' => $status, 'data' => $json];
        } catch (GuzzleException $e) {
            $this->logger?->error('HTTP error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new \RuntimeException('HTTP error: ' . $e->getMessage(), 0, $e);
        } catch (\JsonException $e) {
            $this->logger?->error('JSON parse error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Invalid JSON response: ' . $e->getMessage(), 0, $e);
        }
    }

    /** Builds an absolute URL for a Management API (`/v1`) path. */
    private function v1(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/v1' . $path;
    }

    /** Appends a query string built from scalar params, if any. */
    private function withQuery(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }
        return $url . '?' . http_build_query($params);
    }

    /**
     * Unwraps the Management API response envelope `{ data, pagination? }`,
     * returning the inner `data`. Non-enveloped payloads are returned as-is.
     */
    private function unwrap(mixed $envelope): mixed
    {
        if (is_array($envelope) && array_key_exists('data', $envelope)) {
            return $envelope['data'];
        }
        return $envelope;
    }

    /** ---------------- Public API (DTOs) ---------------- */

    public function getStudioProfile(?string $overrideId = null): StudioProfileDto
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/profile";
        $raw = $this->getJson($url)['data'];
        return StudioProfileDto::fromRaw($raw);
    }

    public function getUserProfile(string $userId): UserProfileDto
    {
        if ($userId === '') {
            throw new \InvalidArgumentException('getUserProfile requires a userId argument');
        }
        $url = rtrim($this->baseUrl, '/') . "/public/user/{$userId}/profile";
        $raw = $this->getJson($url)['data'];
        return UserProfileDto::fromRaw($raw);
    }

    /** @return list<InstructorDto> */
    public function getInstructors(?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/instructors";
        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = InstructorDto::fromRaw($raw);
        }
        return $out;
    }

    /**
     * Retrieves the studio's locations (public, no authentication), primary
     * first, then the rest alphabetically.
     *
     * @return list<StudioLocationDto>
     */
    public function getLocations(?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/locations";
        $rawList = $this->getJson($url)['data'];
        return array_map([StudioLocationDto::class, 'fromRaw'], (array)$rawList);
    }

    /** @return list<StudioClassDto> */
    public function getClasses(?DateTimeImmutable $from = null, ?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $base = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/classes";

        $url = $from ? $base . '?from=' . rawurlencode(Date::formatIsoInstant($from)) : $base;
        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = StudioClassDto::fromRaw($raw);
        }
        return $out;
    }

    /** @return list<StudioClassDto> */
    public function getClassesByType(string $typeName, ?DateTimeImmutable $from = null, ?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        if ($typeName === '') {
            throw new \InvalidArgumentException('getClassesByType requires a typeName argument');
        }

        $base = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/classes/" . rawurlencode($typeName);
        $url = $from ? $base . '?from=' . rawurlencode(Date::formatIsoInstant($from)) : $base;

        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = StudioClassDto::fromRaw($raw);
        }
        return $out;
    }

    /** @return list<StudioEventDto> */
    public function getEvents(?DateTimeImmutable $from = null, ?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $base = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/events";
        $url = $from ? $base . '?from=' . rawurlencode(Date::formatIsoInstant($from)) : $base;

        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = StudioEventDto::fromRaw($raw);
        }
        return $out;
    }

    /**
     * Retrieves active group-class packages available for purchase at a studio,
     * ordered by price ascending.
     *
     * To build the purchase URL for a package use: {appUrl}/buy/{package->id}
     *
     * @return list<StudioPackageDto>
     */
    public function getPackages(?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/packages";
        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = StudioPackageDto::fromRaw($raw);
        }
        return $out;
    }

    /**
     * Retrieves instructors in the studio who offer private lessons,
     * including their weekly availability windows and per-duration pricing.
     *
     * To build the guest booking URL for an instructor use:
     *   {appUrl}/{locale}/book/private-lesson/{instructor->id}
     *
     * @return list<PrivateLessonInstructorDto>
     */
    public function getPrivateLessonInstructors(?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/private-lesson-instructors";
        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = PrivateLessonInstructorDto::fromRaw($raw);
        }
        return $out;
    }

    /**
     * Retrieves the studio-defined custom booking questions for private lessons.
     *
     * These are configured by the studio in the dashboard and shown to students
     * during the private-lesson checkout flow. Most integrations only need this
     * if you are mirroring the booking UI; the hosted checkout page already
     * collects answers automatically.
     *
     * @return list<PrivateLessonQuestionDto>
     */
    public function getPrivateLessonQuestions(?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/public/studio/{$id}/private-lesson-questions";
        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = PrivateLessonQuestionDto::fromRaw($raw);
        }
        return $out;
    }

    /** ---------------- Management API: Auth (/v1/auth) ---------------- */

    /**
     * Authenticates with an email + password and stores the returned tokens on
     * the client for subsequent authenticated calls.
     *
     * @param string $email User email.
     * @param string $password User password.
     * @param string|null $deviceName Optional label for the issued session.
     * @return array<string,mixed> The decoded `AuthTokenResponse`.
     * @throws \RuntimeException on invalid credentials or transport failure.
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $body = ['email' => $email, 'password' => $password];
        if ($deviceName !== null) {
            $body['deviceName'] = $deviceName;
        }

        $data = $this->sendJson('POST', $this->v1('/auth/token'), $body, false)['data'];
        if (is_array($data)) {
            $this->accessToken = $data['accessToken'] ?? $this->accessToken;
            $this->refreshToken = $data['refreshToken'] ?? $this->refreshToken;
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Exchanges the stored refresh token for a fresh access token and updates
     * the stored tokens.
     *
     * @return array<string,mixed> The decoded `AuthTokenResponse`.
     * @throws \RuntimeException when no refresh token is available or the call fails.
     */
    public function refresh(): array
    {
        if (!$this->refreshToken) {
            throw new \RuntimeException('No refresh token available – call login() first.');
        }

        $data = $this->sendJson('POST', $this->v1('/auth/refresh'), [
            'refreshToken' => $this->refreshToken,
        ], false)['data'];

        if (is_array($data)) {
            $this->accessToken = $data['accessToken'] ?? $this->accessToken;
            $this->refreshToken = $data['refreshToken'] ?? $this->refreshToken;
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Revokes the current session's refresh token and clears the stored tokens.
     */
    public function logout(): void
    {
        $body = $this->refreshToken !== null ? ['refreshToken' => $this->refreshToken] : null;
        $this->sendJson('POST', $this->v1('/auth/revoke'), $body, true);
        $this->accessToken = null;
        $this->refreshToken = null;
    }

    /** ---------------- Management API: Classes (/v1/classes) ---------------- */

    /**
     * @param array<string,mixed> $params Optional query params (e.g. from, to, limit, offset, studioTypeId).
     * @return list<ManagementClassDto>
     */
    public function listClasses(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/classes'), $params), true)['data']);
        return array_map([ManagementClassDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Creates a class (or event when `studioTypeId` is omitted). Because a class
     * may recur, the server returns a LIST of created occurrences.
     *
     * Expected `$input` keys: name, discipline, level, startTime (HH:mm),
     * endTime (HH:mm), teamMemberId, date (YYYY-MM-DD), and optionally
     * studioTypeId, roomId, location, repeatUntil (YYYY-MM-DD), capacity, price,
     * allowPackages, requirePackage, allowedPackageIds, isVirtual,
     * virtualPlatform (ZOOM|GOOGLE_MEET|TEAMS), description.
     *
     * @param array<string,mixed> $input
     * @return list<ManagementClassDto>
     */
    public function createClass(array $input): array
    {
        $rows = $this->unwrap($this->sendJson('POST', $this->v1('/classes'), $input, true)['data']);
        return array_map([ManagementClassDto::class, 'fromRaw'], (array)$rows);
    }

    public function getClass(string $id): ManagementClassDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/classes/' . rawurlencode($id)), true)['data']);
        return ManagementClassDto::fromRaw((array)$row);
    }

    /** @param array<string,mixed> $input */
    public function updateClass(string $id, array $input): ManagementClassDto
    {
        $row = $this->unwrap($this->sendJson('PATCH', $this->v1('/classes/' . rawurlencode($id)), $input, true)['data']);
        return ManagementClassDto::fromRaw((array)$row);
    }

    /**
     * @param bool $applyToSeries When true, deletes the whole recurring series.
     * @return mixed The decoded `data` payload.
     */
    public function deleteClass(string $id, bool $applyToSeries = false): mixed
    {
        $url = $this->withQuery(
            $this->v1('/classes/' . rawurlencode($id)),
            $applyToSeries ? ['applyToSeries' => 'true'] : []
        );
        return $this->unwrap($this->sendJson('DELETE', $url, null, true)['data']);
    }

    /** ---------------- Management API: Events (/v1/events) ---------------- */

    /**
     * @param array<string,mixed> $params
     * @return list<ManagementClassDto>
     */
    public function listEvents(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/events'), $params), true)['data']);
        return array_map([ManagementClassDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Creates an event. As with {@see createClass()}, returns a LIST of created
     * occurrences.
     *
     * @param array<string,mixed> $input
     * @return list<ManagementClassDto>
     */
    public function createEvent(array $input): array
    {
        $rows = $this->unwrap($this->sendJson('POST', $this->v1('/events'), $input, true)['data']);
        return array_map([ManagementClassDto::class, 'fromRaw'], (array)$rows);
    }

    public function getEvent(string $id): ManagementClassDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/events/' . rawurlencode($id)), true)['data']);
        return ManagementClassDto::fromRaw((array)$row);
    }

    /** @param array<string,mixed> $input */
    public function updateEvent(string $id, array $input): ManagementClassDto
    {
        $row = $this->unwrap($this->sendJson('PATCH', $this->v1('/events/' . rawurlencode($id)), $input, true)['data']);
        return ManagementClassDto::fromRaw((array)$row);
    }

    /** @return mixed The decoded `data` payload. */
    public function deleteEvent(string $id, bool $applyToSeries = false): mixed
    {
        $url = $this->withQuery(
            $this->v1('/events/' . rawurlencode($id)),
            $applyToSeries ? ['applyToSeries' => 'true'] : []
        );
        return $this->unwrap($this->sendJson('DELETE', $url, null, true)['data']);
    }

    /** ---------------- Management API: Students (/v1/students) ---------------- */

    /**
     * @param array<string,mixed> $params
     * @return list<ManagementStudentDto>
     */
    public function listStudents(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/students'), $params), true)['data']);
        return array_map([ManagementStudentDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Expected `$input` keys: name, lastname, email, level, and optionally phone, status.
     *
     * @param array<string,mixed> $input
     */
    public function createStudent(array $input): ManagementStudentDto
    {
        $row = $this->unwrap($this->sendJson('POST', $this->v1('/students'), $input, true)['data']);
        return ManagementStudentDto::fromRaw((array)$row);
    }

    public function getStudent(string $id): ManagementStudentDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/students/' . rawurlencode($id)), true)['data']);
        return ManagementStudentDto::fromRaw((array)$row);
    }

    /** @param array<string,mixed> $input */
    public function updateStudent(string $id, array $input): ManagementStudentDto
    {
        $row = $this->unwrap($this->sendJson('PATCH', $this->v1('/students/' . rawurlencode($id)), $input, true)['data']);
        return ManagementStudentDto::fromRaw((array)$row);
    }

    /** @return mixed The decoded `data` payload. */
    public function deleteStudent(string $id): mixed
    {
        return $this->unwrap($this->sendJson('DELETE', $this->v1('/students/' . rawurlencode($id)), null, true)['data']);
    }

    /** ---------------- Management API: Instructors (/v1/instructors) ---------------- */

    /**
     * @param array<string,mixed> $params
     * @return list<ManagementTeamMemberDto>
     */
    public function listInstructors(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/instructors'), $params), true)['data']);
        return array_map([ManagementTeamMemberDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Expected `$input` keys: name, email, and optionally lastname, phone.
     *
     * @param array<string,mixed> $input
     */
    public function createInstructor(array $input): ManagementTeamMemberDto
    {
        $row = $this->unwrap($this->sendJson('POST', $this->v1('/instructors'), $input, true)['data']);
        return ManagementTeamMemberDto::fromRaw((array)$row);
    }

    public function getInstructor(string $id): ManagementTeamMemberDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/instructors/' . rawurlencode($id)), true)['data']);
        return ManagementTeamMemberDto::fromRaw((array)$row);
    }

    /** @return mixed The decoded `data` payload. */
    public function deleteInstructor(string $id): mixed
    {
        return $this->unwrap($this->sendJson('DELETE', $this->v1('/instructors/' . rawurlencode($id)), null, true)['data']);
    }

    /** ---------------- Management API: Team (/v1/team) ---------------- */

    /**
     * @param array<string,mixed> $params
     * @return list<ManagementTeamMemberDto>
     */
    public function listTeam(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/team'), $params), true)['data']);
        return array_map([ManagementTeamMemberDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Expected `$input` keys: name, email, role (staff|instructor|owner|admin),
     * and optionally lastname, phone.
     *
     * @param array<string,mixed> $input
     */
    public function createTeamMember(array $input): ManagementTeamMemberDto
    {
        $row = $this->unwrap($this->sendJson('POST', $this->v1('/team'), $input, true)['data']);
        return ManagementTeamMemberDto::fromRaw((array)$row);
    }

    public function getTeamMember(string $id): ManagementTeamMemberDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/team/' . rawurlencode($id)), true)['data']);
        return ManagementTeamMemberDto::fromRaw((array)$row);
    }

    /** @return mixed The decoded `data` payload. */
    public function deleteTeamMember(string $id): mixed
    {
        return $this->unwrap($this->sendJson('DELETE', $this->v1('/team/' . rawurlencode($id)), null, true)['data']);
    }

    /** ---------------- Management API: Packages (/v1/packages) ---------------- */

    /**
     * @param array<string,mixed> $params
     * @return list<ManagementPackageDto>
     */
    public function listPackages(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/packages'), $params), true)['data']);
        return array_map([ManagementPackageDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Expected `$input` keys: name, price, sessions, durationMonths, and
     * optionally description, allowedLevels, allowedDanceTypes, isPrivateLesson,
     * privateDurationMins.
     *
     * @param array<string,mixed> $input
     */
    public function createPackage(array $input): ManagementPackageDto
    {
        $row = $this->unwrap($this->sendJson('POST', $this->v1('/packages'), $input, true)['data']);
        return ManagementPackageDto::fromRaw((array)$row);
    }

    public function getPackage(string $id): ManagementPackageDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/packages/' . rawurlencode($id)), true)['data']);
        return ManagementPackageDto::fromRaw((array)$row);
    }

    /** @param array<string,mixed> $input */
    public function updatePackage(string $id, array $input): ManagementPackageDto
    {
        $row = $this->unwrap($this->sendJson('PATCH', $this->v1('/packages/' . rawurlencode($id)), $input, true)['data']);
        return ManagementPackageDto::fromRaw((array)$row);
    }

    /** @return mixed The decoded `data` payload. */
    public function deletePackage(string $id): mixed
    {
        return $this->unwrap($this->sendJson('DELETE', $this->v1('/packages/' . rawurlencode($id)), null, true)['data']);
    }

    /** ---------------- Management API: Rooms (/v1/rooms) ---------------- */

    /**
     * @param array<string,mixed> $params Optional `limit` / `offset`.
     * @return list<ManagementRoomDto>
     */
    public function listRooms(array $params = []): array
    {
        $rows = $this->unwrap($this->getJson($this->withQuery($this->v1('/rooms'), $params), true)['data']);
        return array_map([ManagementRoomDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Expected `$input` keys: name, and optionally capacity, studioLocationId
     * (defaults to the studio's primary location).
     *
     * @param array<string,mixed> $input
     */
    public function createRoom(array $input): ManagementRoomDto
    {
        $row = $this->unwrap($this->sendJson('POST', $this->v1('/rooms'), $input, true)['data']);
        return ManagementRoomDto::fromRaw((array)$row);
    }

    public function getRoom(string $id): ManagementRoomDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/rooms/' . rawurlencode($id)), true)['data']);
        return ManagementRoomDto::fromRaw((array)$row);
    }

    /** @param array<string,mixed> $input */
    public function updateRoom(string $id, array $input): ManagementRoomDto
    {
        $row = $this->unwrap($this->sendJson('PATCH', $this->v1('/rooms/' . rawurlencode($id)), $input, true)['data']);
        return ManagementRoomDto::fromRaw((array)$row);
    }

    /** @return mixed The decoded `data` payload. */
    public function deleteRoom(string $id): mixed
    {
        return $this->unwrap($this->sendJson('DELETE', $this->v1('/rooms/' . rawurlencode($id)), null, true)['data']);
    }

    /** ---------------- Management API: Locations (/v1/locations) ---------------- */

    /**
     * Lists the active studio's locations (primary first).
     *
     * @return list<StudioLocationDto>
     */
    public function listLocations(): array
    {
        $rows = $this->unwrap($this->getJson($this->v1('/locations'), true)['data']);
        return array_map([StudioLocationDto::class, 'fromRaw'], (array)$rows);
    }

    /**
     * Expected `$input` keys: name, and optionally addressLine1, addressLine2
     * (Suite/Unit/Floor), city, state, postalCode, country, latitude, longitude,
     * isPrimary.
     *
     * @param array<string,mixed> $input
     */
    public function createLocation(array $input): StudioLocationDto
    {
        $row = $this->unwrap($this->sendJson('POST', $this->v1('/locations'), $input, true)['data']);
        return StudioLocationDto::fromRaw((array)$row);
    }

    public function getLocation(string $id): StudioLocationDto
    {
        $row = $this->unwrap($this->getJson($this->v1('/locations/' . rawurlencode($id)), true)['data']);
        return StudioLocationDto::fromRaw((array)$row);
    }

    /**
     * Updates a location and/or promotes it to primary (pass `isPrimary => true`).
     *
     * @param array<string,mixed> $input
     */
    public function updateLocation(string $id, array $input): StudioLocationDto
    {
        $row = $this->unwrap($this->sendJson('PATCH', $this->v1('/locations/' . rawurlencode($id)), $input, true)['data']);
        return StudioLocationDto::fromRaw((array)$row);
    }

    /**
     * Deletes a non-primary location, reassigning its rooms/classes to the
     * primary. Deleting the primary location fails.
     *
     * @return mixed The decoded `data` payload.
     */
    public function deleteLocation(string $id): mixed
    {
        return $this->unwrap($this->sendJson('DELETE', $this->v1('/locations/' . rawurlencode($id)), null, true)['data']);
    }
}

