<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Eloquent\Enumeration\AbstractEnumeration;

/**
 * @method static self UNSUPPORTED
 * @method static self VERIFIED
 * @method static self PLAYABLE
 */
final class SteamDeckCompatibility extends AbstractEnumeration
{
    public const UNSUPPORTED = 'UNSUPPORTED';
    public const VERIFIED = 'VERIFIED';
    public const PLAYABLE = 'PLAYABLE';

    private const ID_MAP = [
        1 => self::UNSUPPORTED,
        2 => self::PLAYABLE,
        3 => self::VERIFIED,
    ];

    public static function fromId(int $id): self
    {
        return self::memberByKey(self::ID_MAP[$id]);
    }

    public function toId(): int
    {
        return array_search($this->key(), self::ID_MAP, true);
    }
}
