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
            'image_hash' => self::parseImageHash($crawler->filter('.playerAvatarAutoSizeInner > img')->attr('src')),
        ];
    }

    private static function parseImageHash(string $imageUrl): string
    {
        if (preg_match('[.*/([\da-f]+)]', $imageUrl, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException("Image URL does not contain hash: \"$imageUrl\".");
    }
}
