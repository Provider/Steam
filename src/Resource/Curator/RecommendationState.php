<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

enum RecommendationState
{
    case RECOMMENDED;
    case INFORMATIONAL;
    case NOT_RECOMMENDED;

    public function toInt(): int
    {
        return match ($this) {
            self::RECOMMENDED => 0,
            self::INFORMATIONAL => 2,
            self::NOT_RECOMMENDED => 1,
        };
    }

    public static function fromInt(int $value): self
    {
        return match ($value) {
            0 => self::RECOMMENDED,
            2 => self::INFORMATIONAL,
            1 => self::NOT_RECOMMENDED,
        };
    }
}
