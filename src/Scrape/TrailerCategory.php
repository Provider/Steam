<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

/**
 * Trailer categories as documented by Valve. The category is displayed alongside the trailer's title in the
 * store page video player.
 *
 * Only categorized trailers carry a category ID; uncategorized trailers (ID 0 or absent) have no label.
 * IDs 4 and 5 have never been observed in the wild and intentionally map to null.
 *
 * @see https://partner.steamgames.com/doc/store/trailer
 */
enum TrailerCategory: int
{
    case GAMEPLAY = 1;
    case TEASER = 2;
    case GENERAL_CINEMATIC = 3;
    case INTERVIEW_DEV_DIARY = 6;

    public function label(): string
    {
        return match ($this) {
            self::GAMEPLAY => 'Gameplay',
            self::TEASER => 'Teaser',
            self::GENERAL_CINEMATIC => 'General / Cinematic',
            self::INTERVIEW_DEV_DIARY => 'Interview / Dev Diary',
        };
    }
}
