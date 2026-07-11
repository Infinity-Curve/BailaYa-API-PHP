<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A physical venue/branch of a studio. Exactly one location per studio is the
 * primary (`isPrimary`). `addressLine2` carries a freeform Suite / Unit / Floor.
 */
final class StudioLocation implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $addressLine1,
        public readonly ?string $addressLine2,
        public readonly ?string $city,
        public readonly ?string $state,
        public readonly ?string $postalCode,
        public readonly ?string $country,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly bool $isPrimary,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            addressLine1: $raw['addressLine1'] ?? null,
            addressLine2: $raw['addressLine2'] ?? null,
            city: $raw['city'] ?? null,
            state: $raw['state'] ?? null,
            postalCode: $raw['postalCode'] ?? null,
            country: $raw['country'] ?? null,
            latitude: isset($raw['latitude']) ? (float)$raw['latitude'] : null,
            longitude: isset($raw['longitude']) ? (float)$raw['longitude'] : null,
            isPrimary: isset($raw['isPrimary']) ? (bool)$raw['isPrimary'] : false,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'addressLine1' => $this->addressLine1,
            'addressLine2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'isPrimary' => $this->isPrimary,
        ];
    }
}
