<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;

final class StudioProfile implements \JsonSerializable
{
    /** @param array<string,string> $description
     *  @param list<StudioType> $studioTypes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $country,
        public readonly string $address,
        public readonly ?string $unit,
        public readonly ?string $businessHours,
        public readonly array $description,
        public readonly ?string $logo,
        public readonly ?string $phone,
        public readonly ?int $studioSize,
        public readonly ?string $website,
        public readonly ?int $yearEstablished,
        public readonly array $studioTypes,
    ) {}

    /** @param array<string,mixed> $raw RawStudioProfile */
    public static function fromRaw(array $raw): self
    {
        $types = [];
        foreach (($raw['studioTypes'] ?? []) as $t) {
            $types[] = StudioType::fromRaw($t);
        }

        return new self(
            id: $raw['id'],
            name: $raw['name'],
            country: $raw['country'],
            address: $raw['address'],
            unit: $raw['unit'] ?? null,
            businessHours: $raw['businessHours'] ?? null,
            description: Json::mapOrEmpty($raw['description'] ?? '{}'),
            logo: $raw['logo'] ?? null,
            phone: $raw['phone'] ?? null,
            studioSize: isset($raw['studioSize']) ? (int)$raw['studioSize'] : null,
            website: $raw['website'] ?? null,
            yearEstablished: isset($raw['yearEstablished']) ? (int)$raw['yearEstablished'] : null,
            studioTypes: $types,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => $this->country,
            'address' => $this->address,
            'unit' => $this->unit,
            'businessHours' => $this->businessHours,
            'description' => $this->description,
            'logo' => $this->logo,
            'phone' => $this->phone,
            'studioSize' => $this->studioSize,
            'website' => $this->website,
            'yearEstablished' => $this->yearEstablished,
            'studioTypes' => $this->studioTypes,
        ];
    }
}
