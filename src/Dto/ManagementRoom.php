<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/** A room as returned by the Management API. A room belongs to a location. */
final class ManagementRoom implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?int $capacity,
        public readonly ?string $studioLocationId,
        public readonly string $studioId,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            capacity: isset($raw['capacity']) ? (int)$raw['capacity'] : null,
            studioLocationId: $raw['studioLocationId'] ?? null,
            studioId: $raw['studioId'],
            createdAt: $raw['createdAt'] ?? null,
            updatedAt: $raw['updatedAt'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'studioLocationId' => $this->studioLocationId,
            'studioId' => $this->studioId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
