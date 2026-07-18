<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/** Full package detail from GET /public/packages/{id}. */
final class PublicPackage implements \JsonSerializable
{
    /**
     * @param list<string> $allowedLevels
     * @param list<string> $allowedDanceTypes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly float $price,
        public readonly int $sessions,
        public readonly int $durationMonths,
        public readonly array $allowedLevels,
        public readonly array $allowedDanceTypes,
        public readonly StudioRef $studio,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            description: $raw['description'] ?? null,
            price: (float)$raw['price'],
            sessions: (int)$raw['sessions'],
            durationMonths: (int)$raw['durationMonths'],
            allowedLevels: array_values($raw['allowedLevels'] ?? []),
            allowedDanceTypes: array_values($raw['allowedDanceTypes'] ?? []),
            studio: StudioRef::fromRaw($raw['studio']),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'sessions' => $this->sessions,
            'durationMonths' => $this->durationMonths,
            'allowedLevels' => $this->allowedLevels,
            'allowedDanceTypes' => $this->allowedDanceTypes,
            'studio' => $this->studio,
        ];
    }
}
