<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

enum SteamDeckCompatibility: int
{
    case UNKNOWN = 0;
    case UNSUPPORTED = 1;
    case PLAYABLE = 2;
    case VERIFIED = 3;

    public static function fromId(int $id): self
    {
        return self::from($id);
    }

    public function toId(): int
    {
        return $this->value;
    }
}
