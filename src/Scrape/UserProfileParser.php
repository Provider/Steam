<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

final class UserProfileParser
{
    use StaticClass;

    public static function parse(Crawler $crawler): array
    {
        return [
            'name' => $crawler->filter('.actual_persona_name')->text(),
            'image_hash' => self::parseImageHash(
                $src = $crawler->filter('.playerAvatarAutoSizeInner > img')->attr('src')
            ),
            'image_path_fragment' => self::parseImagePathFragment($src),
        ];
    }

    private static function parseImageHash(string $imageUrl): string
    {
        if (preg_match('[/([\da-f]{40}(?=\b|_))]', $imageUrl, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException("Image URL does not contain hash: \"$imageUrl\".");
    }

    /**
     * Parses the mutating part of the image path. When non-null, this should be used instead of just the hash.
     */
    private static function parseImagePathFragment(string $imageUrl): ?string
    {
        if (preg_match('[/(\d+/[\da-f]{40}\..{3,10})]', $imageUrl, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
