<?php
declare(strict_types=1);

namespace BailaYa\Dto;

final class PersonRef implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname = null,
        public readonly ?string $image = null,
    ) {}

    /** @param array{id:string,name:string,lastname?:string,image?:string|null} $a */
    public static function fromArray(array $a): self
    {
        return new self(
            id: $a['id'],
            name: $a['name'],
            lastname: $a['lastname'] ?? null,
            image: $a['image'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'image' => $this->image,
        ];
    }
}
