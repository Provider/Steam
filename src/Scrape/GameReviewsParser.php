<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
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
                    'review_playtime' => self::extractReviewPlaytime($card),
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

        // Year is omitted when it matches the current year.
        if (!preg_match('[^Posted: (\w+ \d\d?(?:, \d{4})?)]', $date, $matches)) {
            throw new ParserException("Unexpected date: \"$date\".");
        }

        /*
         * TODO: There is a bug here during the New Year when our computer's clock is ahead of Steam's clock: since year
         * is omitted during the "current" year, we may misrepresent December 31st XXX1 as December 31st XXX2 on January
         * 1st.
        */
        return new \DateTimeImmutable(str_replace(',', '', $matches[1]));
    }

    public static function extractSource(Crawler $crawler): ReviewSource
    {
        $source = $crawler->filter('.review_source')->attr('src');

        if (str_ends_with($source, 'icon_review_counted.png')) {
            return ReviewSource::STEAM;
        }

        if (str_ends_with($source, 'icon_review_notcounted.png')) {
            return ReviewSource::STEAM_KEY;
        }

        throw new ParserException("Invalid resource source: \"$source\".");
    }

    private static function extractReviewPlaytime(Crawler $crawler): ?int
    {
        if (($hoursElement = $crawler->filter('.hours'))->count()) {
            $hours = $hoursElement->first()->text();

            if (preg_match('[\(([\\d,.]+) hrs at review time\)]', $hours, $matches)) {
                return (int)(strtr($matches[1], [',' => '']) * 60);
            }
        }

        return null;
    }
}
