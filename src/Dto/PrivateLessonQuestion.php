<?php
declare(strict_types=1);

namespace BailaYa\Dto;

/**
 * A studio-defined question shown to students when they request a private lesson.
 * Studios configure these via the dashboard; the hosted checkout page collects
 * answers automatically and forwards them to the instructor.
 */
final class PrivateLessonQuestion implements \JsonSerializable
{
    public const TYPE_FREEFORM = 'FREEFORM';
    public const TYPE_DROPDOWN = 'DROPDOWN';

    /**
     * @param array<string,string>                        $label    Question text keyed by locale code.
     * @param 'FREEFORM'|'DROPDOWN'                       $type     Answer input style.
     * @param list<PrivateLessonQuestionOption>|null      $options  Predefined options (only for DROPDOWN).
     */
    public function __construct(
        public readonly string $id,
        public readonly array $label,
        public readonly string $type,
        public readonly ?array $options,
        public readonly bool $required,
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

        $options = null;
        if (isset($raw['options']) && is_array($raw['options'])) {
            $options = [];
            foreach ($raw['options'] as $opt) {
                if (is_array($opt)) {
                    $options[] = PrivateLessonQuestionOption::fromRaw($opt);
                }
            }
        }

        $type = (string)($raw['type'] ?? self::TYPE_FREEFORM);
        if ($type !== self::TYPE_FREEFORM && $type !== self::TYPE_DROPDOWN) {
            $type = self::TYPE_FREEFORM;
        }

        return new self(
            id: (string)($raw['id'] ?? ''),
            label: $label,
            type: $type,
            options: $options,
            required: (bool)($raw['required'] ?? false),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'required' => $this->required,
        ];
    }
}
