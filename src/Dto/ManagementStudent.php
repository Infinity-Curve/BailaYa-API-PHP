<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A student as returned by the authenticated Management API (`/v1/students`).
 */
final class ManagementStudent implements \JsonSerializable
{
    /**
     * @param array{id:string,name:?string,lastname:?string,email:?string}|null $user
     *        The linked login account, when the student has one. Omitted by the
     *        server for students who have never registered.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $lastname,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $level,
        public readonly string $status,
        public readonly ?string $userId,
        public readonly string $studioId,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?array $user = null,
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
            level:     $raw['level'] ?? '',
            status:    $raw['status'] ?? '',
            userId:    $raw['userId'] ?? null,
            studioId:  (string)($raw['studioId'] ?? ''),
            createdAt: (string)($raw['createdAt'] ?? ''),
            updatedAt: (string)($raw['updatedAt'] ?? ''),
            user:      isset($raw['user']) && is_array($raw['user']) ? $raw['user'] : null,
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
            'level'     => $this->level,
            'status'    => $this->status,
            'userId'    => $this->userId,
            'user'      => $this->user,
            'studioId'  => $this->studioId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
