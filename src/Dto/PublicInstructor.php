<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/** Full instructor detail with availability and pricing from GET /public/instructors/{id}. */
final class PublicInstructor implements \JsonSerializable
{
    /**
     * @param array<string,mixed>                  $studio
     * @param list<PrivateLessonAvailabilitySlot>  $instructorAvailability
     * @param list<PrivateLessonPricingEntry>      $privateLessonPricing
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname,
        public readonly string $studioId,
        public readonly array $studio,
        public readonly array $instructorAvailability,
        public readonly array $privateLessonPricing,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        $availability = [];
        foreach (($raw['instructorAvailability'] ?? []) as $slot) {
            $availability[] = PrivateLessonAvailabilitySlot::fromRaw($slot);
        }

        $pricing = [];
        foreach (($raw['privateLessonPricing'] ?? []) as $entry) {
            $pricing[] = PrivateLessonPricingEntry::fromRaw($entry);
        }

        return new self(
            id: $raw['id'],
            name: $raw['name'],
            lastname: $raw['lastname'] ?? null,
            studioId: $raw['studioId'],
            studio: $raw['studio'] ?? [],
            instructorAvailability: $availability,
            privateLessonPricing: $pricing,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'studioId' => $this->studioId,
            'studio' => $this->studio,
            'instructorAvailability' => $this->instructorAvailability,
            'privateLessonPricing' => $this->privateLessonPricing,
        ];
    }
}
