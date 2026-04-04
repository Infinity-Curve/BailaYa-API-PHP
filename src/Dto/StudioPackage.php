<?php
declare(strict_types=1);

namespace BailaYa\Dto;

use BailaYa\Support\Json;

final class StudioPackage implements \JsonSerializable
{
    /**
     * @param array<string,string> $description
     *
     * To build the purchase URL use: {appUrl}/buy/{id}
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array  $description,
        public readonly float  $price,
        public readonly int    $sessions,
        public readonly int    $durationMonths,
        public readonly string $currency,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        $desc = $raw['description'] ?? null;
        if (is_string($desc)) {
            $description = Json::mapOrEmpty($desc);
        } elseif (is_array($desc)) {
            $description = array_map('strval', $desc);
        } else {
            $description = [];
        }

        return new self(
            id:             $raw['id'],
            name:           $raw['name'],
            description:    $description,
            price:          (float)$raw['price'],
            sessions:       (int)$raw['sessions'],
            durationMonths: (int)$raw['durationMonths'],
            currency:       $raw['currency'] ?? 'USD',
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'description'    => $this->description,
            'price'          => $this->price,
            'sessions'       => $this->sessions,
            'durationMonths' => $this->durationMonths,
            'currency'       => $this->currency,
        ];
    }
}
