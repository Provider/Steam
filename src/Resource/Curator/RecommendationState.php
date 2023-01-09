<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Eloquent\Enumeration\AbstractEnumeration;

/**
 * @method static self RECOMMENDED
 * @method static self INFORMATIONAL
 * @method static self NOT_RECOMMENDED
 */
final class RecommendationState extends AbstractEnumeration
{
    public const RECOMMENDED = 'RECOMMENDED';
    public const INFORMATIONAL = 'INFORMATIONAL';
    public const NOT_RECOMMENDED = 'NOT_RECOMMENDED';

    public function toInt(): int
    {
        return match ($this) {
            self::RECOMMENDED() => 0,
            self::INFORMATIONAL() => 2,
            self::NOT_RECOMMENDED() => 1,
        };
    }
}
