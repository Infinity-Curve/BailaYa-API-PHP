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
        /**
         * Whether this dance type has at least one class in the upcoming window the
         * schedule endpoints serve.
         *
         * Use it to hide a style that would lead to an empty schedule page. Null on
         * older API deployments, which callers should treat as "show it".
         */
        public readonly ?bool $hasUpcomingClasses = null,
    ) {}

    /** @param array{name:string,description:string,image?:string,isVisibleOnWeb?:bool,hasUpcomingClasses?:bool} $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            name: $raw['name'],
            description: Json::mapOrEmpty($raw['description'] ?? '{}'),
            image: $raw['image'] ?? null,
            isVisibleOnWeb: isset($raw['isVisibleOnWeb']) ? (bool)$raw['isVisibleOnWeb'] : false,
            hasUpcomingClasses: isset($raw['hasUpcomingClasses']) ? (bool)$raw['hasUpcomingClasses'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'isVisibleOnWeb' => $this->isVisibleOnWeb,
            'hasUpcomingClasses' => $this->hasUpcomingClasses,
        ];
    }
}
