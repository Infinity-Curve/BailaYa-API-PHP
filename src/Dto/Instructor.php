<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;

final class Instructor implements \JsonSerializable
{
    /** @param array<string,string> $bio */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname,
        public readonly ?string $image,
        public readonly array $bio,
    ) {}

    /** @param array{id:string,name:string,lastname?:string,image?:string,bio:string} $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            lastname: $raw['lastname'] ?? null,
            image: $raw['image'] ?? null,
            bio: Json::mapOrEmpty($raw['bio'] ?? '{}'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'image' => $this->image,
            'bio' => $this->bio,
        ];
    }
}
