<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Date;
use DateTimeImmutable;

/** Full class detail from GET /public/classes/{id}, including live enrolment counts. */
final class PublicClass implements \JsonSerializable
{
    /**
     * @param array{name:string,lastname?:?string}|null $instructor
     * @param array<string,string>|null                 $description
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $level,
        public readonly DateTimeImmutable $date,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly float $price,
        public readonly int $capacity,
        public readonly int $enrolledCount,
        public readonly ?int $waitlistedCount,
        public readonly int $availableSpots,
        public readonly ?bool $isRecurring,
        public readonly ?ClassLocation $location,
        public readonly ?array $description,
        public readonly ?array $instructor,
        public readonly ?string $room,
        public readonly StudioRef $studio,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            level: $raw['level'],
            date: Date::parseApiDateUTC($raw['date']),
            startTime: $raw['startTime'],
            endTime: $raw['endTime'],
            price: (float)$raw['price'],
            capacity: (int)$raw['capacity'],
            enrolledCount: (int)$raw['enrolledCount'],
            waitlistedCount: isset($raw['waitlistedCount']) ? (int)$raw['waitlistedCount'] : null,
            availableSpots: (int)$raw['availableSpots'],
            isRecurring: isset($raw['isRecurring']) ? (bool)$raw['isRecurring'] : null,
            location: isset($raw['location']) && is_array($raw['location'])
                ? ClassLocation::fromArray($raw['location'])
                : null,
            description: isset($raw['description']) && is_array($raw['description']) ? $raw['description'] : null,
            instructor: isset($raw['instructor']) && is_array($raw['instructor']) ? $raw['instructor'] : null,
            room: isset($raw['room']) && is_array($raw['room']) ? ($raw['room']['name'] ?? null) : null,
            studio: StudioRef::fromRaw($raw['studio']),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'date' => $this->date->format(DATE_ATOM),
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'price' => $this->price,
            'capacity' => $this->capacity,
            'enrolledCount' => $this->enrolledCount,
            'waitlistedCount' => $this->waitlistedCount,
            'availableSpots' => $this->availableSpots,
            'isRecurring' => $this->isRecurring,
            'location' => $this->location,
            'description' => $this->description,
            'instructor' => $this->instructor,
            'room' => $this->room,
            'studio' => $this->studio,
        ];
    }
}
