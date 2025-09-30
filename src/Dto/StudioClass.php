<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Date;
use DateTimeImmutable;

final class StudioClass implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $dayOfWeek,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly string $level,
        public readonly string $room,
        public readonly DateTimeImmutable $date,
        public readonly float|int $price,
        public readonly int $capacity,
        public readonly bool $allowPackages,
        public readonly ?PersonRef $instructor,
    ) {}

    /** @param array<string,mixed> $raw RawStudioClass */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            dayOfWeek: $raw['dayOfWeek'],
            startTime: $raw['startTime'],
            endTime: $raw['endTime'],
            level: $raw['level'],
            room: $raw['room'],
            date: Date::parseApiDateUTC($raw['date']),
            price: $raw['price'],
            capacity: (int)$raw['capacity'],
            allowPackages: (bool)$raw['allowPackages'],
            instructor: isset($raw['instructor']) && is_array($raw['instructor'])
                ? PersonRef::fromArray($raw['instructor'])
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dayOfWeek' => $this->dayOfWeek,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'level' => $this->level,
            'room' => $this->room,
            'date' => $this->date->format(DATE_ATOM),
            'price' => $this->price,
            'capacity' => $this->capacity,
            'allowPackages' => $this->allowPackages,
            'instructor' => $this->instructor,
        ];
    }
}
