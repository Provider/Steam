<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Eloquent\Enumeration\AbstractEnumeration;

/**
 * @method static self STEAM
 * @method static self STEAM_KEY
 */
final class ReviewSource extends AbstractEnumeration
{
    public const STEAM = 'STEAM';
    public const STEAM_KEY = 'STEAM_KEY';
}
