<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A package as returned by the authenticated Management API (`/v1/packages`).
 */
final class ManagementPackage implements \JsonSerializable
{
    /**
     * @param list<string> $allowedLevels
     * @param list<string> $allowedDanceTypes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float|int $price,
        public readonly int $sessions,
        public readonly int $durationMonths,
        public readonly bool $isActive,
        public readonly array $allowedLevels,
        public readonly array $allowedDanceTypes,
        public readonly bool $isPrivateLesson,
        public readonly ?int $privateDurationMins,
        public readonly ?string $deactivatedAt,
        public readonly string $studioId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id:                  $raw['id'],
            name:                $raw['name'],
            description:         $raw['description'] ?? null,
            price:               $raw['price'] ?? 0,
            sessions:            (int)($raw['sessions'] ?? 0),
            durationMonths:      (int)($raw['durationMonths'] ?? 0),
            isActive:            (bool)($raw['isActive'] ?? false),
            allowedLevels:       isset($raw['allowedLevels']) && is_array($raw['allowedLevels'])
                ? array_values(array_map('strval', $raw['allowedLevels']))
                : [],
            allowedDanceTypes:   isset($raw['allowedDanceTypes']) && is_array($raw['allowedDanceTypes'])
                ? array_values(array_map('strval', $raw['allowedDanceTypes']))
                : [],
            isPrivateLesson:     (bool)($raw['isPrivateLesson'] ?? false),
            privateDurationMins: isset($raw['privateDurationMins']) ? (int)$raw['privateDurationMins'] : null,
            deactivatedAt:       $raw['deactivatedAt'] ?? null,
            studioId:            (string)($raw['studioId'] ?? ''),
            createdAt:           (string)($raw['createdAt'] ?? ''),
            updatedAt:           (string)($raw['updatedAt'] ?? ''),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'description'         => $this->description,
            'price'               => $this->price,
            'sessions'            => $this->sessions,
            'durationMonths'      => $this->durationMonths,
            'isActive'            => $this->isActive,
            'allowedLevels'       => $this->allowedLevels,
            'allowedDanceTypes'   => $this->allowedDanceTypes,
            'isPrivateLesson'     => $this->isPrivateLesson,
            'privateDurationMins' => $this->privateDurationMins,
            'deactivatedAt'       => $this->deactivatedAt,
            'studioId'            => $this->studioId,
            'createdAt'           => $this->createdAt,
            'updatedAt'           => $this->updatedAt,
        ];
    }
}
