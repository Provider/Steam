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
}
