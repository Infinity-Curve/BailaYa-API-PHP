<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A class or event as returned by the authenticated Management API (`/v1`).
 * All dates are kept as ISO 8601 strings exactly as returned by the server.
 */
final class ManagementClass implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly mixed $description,
        public readonly string $discipline,
        public readonly string $level,
        public readonly ?string $room,
        public readonly ?string $roomId,
        public readonly ?string $location,
        public readonly string $date,
        public readonly string $dayOfWeek,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly ?int $capacity,
        public readonly float|int|null $price,
        public readonly ?string $studioTypeId,
        public readonly ?string $teamMemberId,
        public readonly ?string $classTemplateId,
        public readonly ?string $recurrenceId,
        public readonly bool $isException,
        public readonly bool $allowPackages,
        public readonly bool $requirePackage,
        public readonly bool $isVirtual,
        public readonly ?string $virtualPlatform,
        public readonly ?string $virtualMeetingUrl,
        public readonly string $studioId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id:                $raw['id'],
            name:              $raw['name'],
            description:       $raw['description'] ?? null,
            discipline:        $raw['discipline'] ?? '',
            level:             $raw['level'] ?? '',
            room:              $raw['room'] ?? null,
            roomId:            $raw['roomId'] ?? null,
            location:          $raw['location'] ?? null,
            date:              (string)($raw['date'] ?? ''),
            dayOfWeek:         $raw['dayOfWeek'] ?? '',
            startTime:         $raw['startTime'] ?? '',
            endTime:           $raw['endTime'] ?? '',
            capacity:          isset($raw['capacity']) ? (int)$raw['capacity'] : null,
            price:             $raw['price'] ?? null,
            studioTypeId:      $raw['studioTypeId'] ?? null,
            teamMemberId:      $raw['teamMemberId'] ?? null,
            classTemplateId:   $raw['classTemplateId'] ?? null,
            recurrenceId:      $raw['recurrenceId'] ?? null,
            isException:       (bool)($raw['isException'] ?? false),
            allowPackages:     (bool)($raw['allowPackages'] ?? false),
            requirePackage:    (bool)($raw['requirePackage'] ?? false),
            isVirtual:         (bool)($raw['isVirtual'] ?? false),
            virtualPlatform:   $raw['virtualPlatform'] ?? null,
            virtualMeetingUrl: $raw['virtualMeetingUrl'] ?? null,
            studioId:          (string)($raw['studioId'] ?? ''),
            createdAt:         (string)($raw['createdAt'] ?? ''),
            updatedAt:         (string)($raw['updatedAt'] ?? ''),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'discipline'        => $this->discipline,
            'level'             => $this->level,
            'room'              => $this->room,
            'roomId'            => $this->roomId,
            'location'          => $this->location,
            'date'              => $this->date,
            'dayOfWeek'         => $this->dayOfWeek,
            'startTime'         => $this->startTime,
            'endTime'           => $this->endTime,
            'capacity'          => $this->capacity,
            'price'             => $this->price,
            'studioTypeId'      => $this->studioTypeId,
            'teamMemberId'      => $this->teamMemberId,
            'classTemplateId'   => $this->classTemplateId,
            'recurrenceId'      => $this->recurrenceId,
            'isException'       => $this->isException,
            'allowPackages'     => $this->allowPackages,
            'requirePackage'    => $this->requirePackage,
            'isVirtual'         => $this->isVirtual,
            'virtualPlatform'   => $this->virtualPlatform,
            'virtualMeetingUrl' => $this->virtualMeetingUrl,
            'studioId'          => $this->studioId,
            'createdAt'         => $this->createdAt,
            'updatedAt'         => $this->updatedAt,
        ];
    }
}
