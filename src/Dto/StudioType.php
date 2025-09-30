<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;

final class StudioType implements \JsonSerializable
{
    /** @param array<string,string> $description */
    public function __construct(
        public readonly string $name,
        public readonly array $description,
        public readonly ?string $image = null,
    ) {}

    /** @param array{name:string,description:string,image?:string} $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            name: $raw['name'],
            description: Json::mapOrEmpty($raw['description'] ?? '{}'),
            image: $raw['image'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
        ];
    }
}
