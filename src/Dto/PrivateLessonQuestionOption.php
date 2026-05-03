<?php
declare(strict_types=1);

namespace BailaYa\Dto;

final class PrivateLessonQuestionOption implements \JsonSerializable
{
    /**
     * @param array<string,string> $label  Display label keyed by locale code.
     */
    public function __construct(
        /** Stable internal value sent back when the option is chosen. */
        public readonly string $value,
        public readonly array $label,
    ) {}

    /** @param array<string,mixed> $raw */
    public static function fromRaw(array $raw): self
    {
        $label = [];
        $rawLabel = $raw['label'] ?? [];
        if (is_array($rawLabel)) {
            foreach ($rawLabel as $loc => $text) {
                if (is_string($loc) && is_string($text)) {
                    $label[$loc] = $text;
                }
            }
        }
        return new self(
            value: (string)($raw['value'] ?? ''),
            label: $label,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
        ];
    }
}
