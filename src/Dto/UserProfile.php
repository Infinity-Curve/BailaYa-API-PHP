<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;

final class UserProfile implements \JsonSerializable
{
    /** @param array<string,string> $bio
     *  @param list<string> $danceSpecialities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname,
        public readonly ?string $image,
        public readonly array $bio,
        public readonly ?string $occupation,
        public readonly ?int $yearsOfExperience,
        public readonly array $danceSpecialities,
    ) {}

    /** @param array<string,mixed> $raw RawUserProfile */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            lastname: $raw['lastname'] ?? null,
            image: $raw['image'] ?? null,
            bio: Json::mapOrEmpty($raw['bio'] ?? '{}'),
            occupation: $raw['occupation'] ?? null,
            yearsOfExperience: isset($raw['yearsOfExperience']) ? (int)$raw['yearsOfExperience'] : null,
            danceSpecialities: array_values($raw['danceSpecialities'] ?? []),
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
            'occupation' => $this->occupation,
            'yearsOfExperience' => $this->yearsOfExperience,
            'danceSpecialities' => $this->danceSpecialities,
        ];
    }
}
