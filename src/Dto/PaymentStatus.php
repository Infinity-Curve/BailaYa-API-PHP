<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/** Payment and associated booking status from GET /public/payments/{paymentId}. */
final class PaymentStatus implements \JsonSerializable
{
    /**
     * @param 'enrolled'|'waitlisted'|'unknown' $bookingStatus
     * @param array<string,mixed>|null          $packageInfo
     */
    public function __construct(
        public readonly PaymentRecord $payment,
        public readonly string $bookingStatus,
        public readonly ?PublicClass $classInfo,
        public readonly ?array $packageInfo,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            payment: PaymentRecord::fromRaw($raw['payment']),
            bookingStatus: $raw['bookingStatus'] ?? 'unknown',
            classInfo: isset($raw['classInfo']) && is_array($raw['classInfo'])
                ? PublicClass::fromRaw($raw['classInfo'])
                : null,
            packageInfo: isset($raw['packageInfo']) && is_array($raw['packageInfo'])
                ? $raw['packageInfo']
                : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'payment' => $this->payment,
            'bookingStatus' => $this->bookingStatus,
            'classInfo' => $this->classInfo,
            'packageInfo' => $this->packageInfo,
        ];
    }
}
