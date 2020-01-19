<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use ScriptFUSION\Type\StringType;
use Symfony\Component\DomCrawler\Crawler;

final class GameReviewsParser
{
    use StaticClass;

    private const TITLES = ['Recommended', 'Not Recommended'];

    public static function parse(Crawler $crawler): array
    {
        return $crawler->filter('.review_box')->each(
            static function (Crawler $card): array {
                return [
                    'review_id' => self::extractReviewId($card),
                    'user_id' => self::extractUserId($card),
                    'positive' => self::extractPositive($card),
                    'date' => self::extractDate($card),
                    'source' => self::extractSource($card),
                ];
            }
        );
    }

    private static function extractReviewId(Crawler $crawler): int
    {
        $div = $crawler->filter('div[id^=ReviewContentrecent]');

        return (int)preg_replace('[^\\D+]', '', $div->attr('id'));
    }

    private static function extractUserId(Crawler $crawler): int
    {
        $uid = (int)$crawler->filter('.playerAvatar')->attr('data-miniprofile');

        if ($uid < 1 || $uid > 0xFFFFFFFF) {
            throw new ParserException("Invalid Steam user ID: \"$uid\".");
        }

        return $uid;
    }

    private static function extractPositive(Crawler $crawler): bool
    {
        $title = $crawler->filter('.title')->text();

        if (!in_array($title, self::TITLES, true)) {
            throw new ParserException("Unexpected review title: \"$title\".");
        }

        return $title === self::TITLES[0];
    }

    private static function extractDate(Crawler $crawler): \DateTimeImmutable
    {
        $date = $crawler->filter('.postedDate')->text();

        if (!StringType::startsWith($date, $prefix = 'Posted: ')) {
            throw new ParserException("Unexpected date: \"$date\".");
        }

        return new \DateTimeImmutable(rtrim(strtr(substr($date, strlen($prefix)), [',' => ''])));
    }

    public static function extractSource(Crawler $crawler): ReviewSource
    {
        $source = $crawler->filter('.review_source')->attr('src');

        if (StringType::endsWith($source, 'icon_review_steam.png')) {
            return ReviewSource::STEAM();
        }

        if (StringType::endsWith($source, 'icon_review_key.png')) {
            return ReviewSource::STEAM_KEY();
        }

        throw new ParserException("Invalid resource source: \"$source\".");
    }
}
