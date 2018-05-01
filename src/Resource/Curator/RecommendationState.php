<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Eloquent\Enumeration\AbstractEnumeration;

/**
 * @method static RECOMMENDED()
 * @method static INFORMATIONAL()
 * @method static NOT_RECOMMENDED()
 */
final class RecommendationState extends AbstractEnumeration
{
    public const RECOMMENDED = 'RECOMMENDED';
    public const INFORMATIONAL = 'INFORMATIONAL';
    public const NOT_RECOMMENDED = 'NOT_RECOMMENDED';

    public function toInt(): int
    {
        switch ($this) {
            case self::RECOMMENDED:
                return 0;
            case self::INFORMATIONAL:
                return 2;
            case self::NOT_RECOMMENDED:
                return 1;
        }
    }
}
