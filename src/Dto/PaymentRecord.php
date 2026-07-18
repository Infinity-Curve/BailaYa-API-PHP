<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use DateTimeImmutable;

/** A payment record embedded in a {@see PaymentStatus} response, with parsed dates. */
final class PaymentRecord implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly string $type,
        public readonly float $amount,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $paidAt,
        public readonly ?string $guestEmail,
        public readonly ?string $userId,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            status: $raw['status'],
            type: $raw['type'],
            amount: (float)$raw['amount'],
            createdAt: new DateTimeImmutable($raw['createdAt']),
            paidAt: isset($raw['paidAt']) && $raw['paidAt'] !== null
                ? new DateTimeImmutable($raw['paidAt'])
                : null,
            guestEmail: $raw['guestEmail'] ?? null,
            userId: $raw['userId'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'type' => $this->type,
            'amount' => $this->amount,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'paidAt' => $this->paidAt?->format(DATE_ATOM),
            'guestEmail' => $this->guestEmail,
            'userId' => $this->userId,
        ];
    }
}
