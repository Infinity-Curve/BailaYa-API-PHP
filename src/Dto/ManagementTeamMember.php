<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A team member (instructor / staff / owner / admin) as returned by the
 * authenticated Management API (`/v1/instructors`, `/v1/team`).
 */
final class ManagementTeamMember implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $role,
        public readonly string $status,
        public readonly ?string $image,
        public readonly ?string $userId,
        public readonly string $studioId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id:        $raw['id'],
            name:      $raw['name'],
            lastname:  $raw['lastname'] ?? null,
            email:     (string)($raw['email'] ?? ''),
            phone:     $raw['phone'] ?? null,
            role:      $raw['role'] ?? '',
            status:    $raw['status'] ?? '',
            image:     $raw['image'] ?? null,
            userId:    $raw['userId'] ?? null,
            studioId:  (string)($raw['studioId'] ?? ''),
            createdAt: (string)($raw['createdAt'] ?? ''),
            updatedAt: (string)($raw['updatedAt'] ?? ''),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'lastname'  => $this->lastname,
            'email'     => $this->email,
            'phone'     => $this->phone,
            'role'      => $this->role,
            'status'    => $this->status,
            'image'     => $this->image,
            'userId'    => $this->userId,
            'studioId'  => $this->studioId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
