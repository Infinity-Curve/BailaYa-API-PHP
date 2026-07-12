<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A dance type as returned by the Management API.
 *
 * Unlike the public {@see StudioType}, this carries the `id` that
 * `createClass()` expects as `studioTypeId`, and includes types hidden from the
 * public site (`isVisibleOnWeb: false`). `description` is the raw stored value —
 * usually a JSON string of localized text — and is left unparsed, as everywhere
 * else in the Management API.
 */
final class ManagementStudioType implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $image,
        public readonly bool $isVisibleOnWeb,
        public readonly ?string $studioId,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            description: $raw['description'] ?? null,
            image: $raw['image'] ?? null,
            isVisibleOnWeb: (bool)($raw['isVisibleOnWeb'] ?? false),
            studioId: $raw['studioId'] ?? null,
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'isVisibleOnWeb' => $this->isVisibleOnWeb,
            'studioId' => $this->studioId,
        ];
    }
}
