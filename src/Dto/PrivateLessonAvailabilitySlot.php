<?php
declare(strict_types=1);

namespace BailaYa\Dto;

final class PrivateLessonAvailabilitySlot implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        /** 0 = Sunday … 6 = Saturday */
        public readonly int $dayOfWeek,
        /** 24-h format, e.g. "09:00" */
        public readonly string $startTime,
        /** 24-h format, e.g. "17:00" */
        public readonly string $endTime,
    ) {}

    /** @param array{id:string,dayOfWeek:int,startTime:string,endTime:string} $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            dayOfWeek: (int)$raw['dayOfWeek'],
            startTime: $raw['startTime'],
            endTime: $raw['endTime'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'dayOfWeek' => $this->dayOfWeek,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
        ];
    }
}
