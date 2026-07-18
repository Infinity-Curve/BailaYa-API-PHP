<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/** Compact studio reference embedded in single-resource detail responses. */
final class StudioRef implements \JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $defaultCurrency,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        return new self(
            id: $raw['id'],
            name: $raw['name'],
            address: $raw['address'] ?? null,
            defaultCurrency: $raw['defaultCurrency'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'defaultCurrency' => $this->defaultCurrency,
        ];
    }
}
