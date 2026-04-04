<?php
declare(strict_types=1);

namespace BailaYa\Dto;

final class PrivateLessonPricingEntry implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        /** Lesson duration in minutes, e.g. 30, 45, 60, 90 */
        public readonly int $durationMins,
        public readonly float $price,
        /** ISO 4217 currency code, e.g. "USD" */
        public readonly string $currency,
    ) {}

    /** @param array{id:string,durationMins:int,price:float|int,currency:string} $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            durationMins: (int)$raw['durationMins'],
            price: (float)$raw['price'],
            currency: $raw['currency'],
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'durationMins' => $this->durationMins,
            'price' => $this->price,
            'currency' => $this->currency,
        ];
    }
}
