<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;

final class PrivateLessonInstructor implements \JsonSerializable
{
    /**
     * @param array<string,string>               $bio
     * @param list<PrivateLessonAvailabilitySlot> $availability
     * @param list<PrivateLessonPricingEntry>     $pricing
     *
     * To build the guest booking URL use:
     *   {appUrl}/{locale}/book/private-lesson/{id}
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname,
        public readonly ?string $image,
        public readonly array $bio,
        public readonly array $availability,
        public readonly array $pricing,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        $availability = [];
        foreach (($raw['availability'] ?? []) as $slot) {
            $availability[] = PrivateLessonAvailabilitySlot::fromRaw($slot);
        }

        $pricing = [];
        foreach (($raw['pricing'] ?? []) as $entry) {
            $pricing[] = PrivateLessonPricingEntry::fromRaw($entry);
        }

        return new self(
            id: $raw['id'],
            name: $raw['name'],
            lastname: $raw['lastname'] ?? null,
            image: $raw['image'] ?? null,
            bio: Json::mapOrEmpty($raw['bio'] ?? null),
            availability: $availability,
            pricing: $pricing,
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
            'availability' => $this->availability,
            'pricing' => $this->pricing,
        ];
    }
}
