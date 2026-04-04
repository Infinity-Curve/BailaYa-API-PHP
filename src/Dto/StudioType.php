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
        public readonly bool $isVisibleOnWeb = false,
    ) {}

    /** @param array{name:string,description:string,image?:string,isVisibleOnWeb?:bool} $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            name: $raw['name'],
            description: Json::mapOrEmpty($raw['description'] ?? '{}'),
            image: $raw['image'] ?? null,
            isVisibleOnWeb: isset($raw['isVisibleOnWeb']) ? (bool)$raw['isVisibleOnWeb'] : false,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'isVisibleOnWeb' => $this->isVisibleOnWeb,
        ];
    }
}
