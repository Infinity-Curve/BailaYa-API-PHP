<?php
declare(strict_types=1);

namespace BailaYa;

use BailaYa\Dto\Instructor as InstructorDto;
use BailaYa\Dto\StudioClass as StudioClassDto;
use BailaYa\Dto\StudioEvent as StudioEventDto;
use BailaYa\Dto\StudioProfile as StudioProfileDto;
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
    private HttpClient $http;
    private ?LoggerInterface $logger;

    /**
     * @param array{baseUrl?:string,studioId?:string,guzzle?:array<string,mixed>} $options
     */
    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        if (is_readable(getcwd().'/.env')) {
            $dotenv = Dotenv::createImmutable(getcwd());
            $dotenv->safeLoad();
        }

        $this->baseUrl = $options['baseUrl'] ?? 'https://www.bailaya.com/api';
        $this->studioId = $options['studioId'] ?? self::getEnv('BAILAYA_STUDIO_ID');
        $this->http = new HttpClient($options['guzzle'] ?? []);
        $this->logger = $logger;
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
     * @template T of array
     * @param string $url
     * @return array{status:int,data:array}
     */
    private function getJson(string $url): array
    {
        try {
            $res = $this->http->get($url, [
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => false,
            ]);

            $status = $res->getStatusCode();
            $body = (string)$res->getBody();

            if ($status < 200 || $status >= 300) {
                $this->logger?->error('API request failed', ['url' => $url, 'status' => $status, 'body' => $body]);
                throw new \RuntimeException("Request failed: {$status} {$res->getReasonPhrase()}");
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

    /** ---------------- Public API (DTOs) ---------------- */

    public function getStudioProfile(?string $overrideId = null): StudioProfileDto
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/studio/{$id}/profile";
        $raw = $this->getJson($url)['data'];
        return StudioProfileDto::fromRaw($raw);
    }

    public function getUserProfile(string $userId): UserProfileDto
    {
        if ($userId === '') {
            throw new \InvalidArgumentException('getUserProfile requires a userId argument');
        }
        $url = rtrim($this->baseUrl, '/') . "/user/{$userId}/profile";
        $raw = $this->getJson($url)['data'];
        return UserProfileDto::fromRaw($raw);
    }

    /** @return list<InstructorDto> */
    public function getInstructors(?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $url = rtrim($this->baseUrl, '/') . "/studio/{$id}/instructors";
        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = InstructorDto::fromRaw($raw);
        }
        return $out;
    }

    /** @return list<StudioClassDto> */
    public function getClasses(?DateTimeImmutable $from = null, ?string $overrideId = null): array
    {
        $id = $this->requireStudioId($overrideId);
        $base = rtrim($this->baseUrl, '/') . "/studio/{$id}/classes";

        $url = $from ? $base . '?from=' . rawurlencode(Date::formatUTCDate($from)) : $base;
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

        $base = rtrim($this->baseUrl, '/') . "/studio/{$id}/classes/" . rawurlencode($typeName);
        $url = $from ? $base . '?from=' . rawurlencode(Date::formatUTCDate($from)) : $base;

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
        $base = rtrim($this->baseUrl, '/') . "/studio/{$id}/events";
        $url = $from ? $base . '?from=' . rawurlencode(Date::formatUTCDate($from)) : $base;

        $rawList = $this->getJson($url)['data'];

        $out = [];
        foreach ($rawList as $raw) {
            $out[] = StudioEventDto::fromRaw($raw);
        }
        return $out;
    }
}
