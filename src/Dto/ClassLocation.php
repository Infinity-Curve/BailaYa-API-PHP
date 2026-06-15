<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * Resolved, display-ready location for a class or event.
 *
 * The API resolves this with the precedence: per-class location override →
 * assigned room's address → the studio's composed address. For virtual classes
 * $isVirtual is true and $address/$mapsUrl are null (the meeting URL is never
 * exposed on public endpoints).
 */
final class ClassLocation implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $roomName,
        public readonly ?string $address,
        public readonly ?string $mapsUrl,
        public readonly bool $isVirtual,
        public readonly ?string $virtualPlatform,
    ) {}

    /** @param array<string,mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            roomName: $a['roomName'] ?? null,
            address: $a['address'] ?? null,
            mapsUrl: $a['mapsUrl'] ?? null,
            isVirtual: (bool)($a['isVirtual'] ?? false),
            virtualPlatform: $a['virtualPlatform'] ?? null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'roomName' => $this->roomName,
            'address' => $this->address,
            'mapsUrl' => $this->mapsUrl,
            'isVirtual' => $this->isVirtual,
            'virtualPlatform' => $this->virtualPlatform,
        ];
    }
}
