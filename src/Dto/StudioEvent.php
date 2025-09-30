<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Date;
use DateTimeImmutable;

final class StudioEvent implements \JsonSerializable
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
        public readonly ?PersonRef $host,
    ) {}

    /** @param array<string,mixed> $raw RawStudioEvent (note: uses "host" not "instructor") */
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
            host: isset($raw['host']) && is_array($raw['host'])
                ? PersonRef::fromArray($raw['host'])
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
            'host' => $this->host,
        ];
    }
}
