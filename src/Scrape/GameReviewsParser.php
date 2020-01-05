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
        self::validate($crawler);

        return [
            'reviews' => $crawler->filter('.apphub_Card')->each(
                static function (Crawler $card): array {
                    return [
                        'uid' => self::extractUserId($card),
                        'positive' => self::extractPositive($card),
                        'date' => self::extractDate($card),
                    ];
                }
            ),
            'form' => $crawler->filter('form')->form()->getValues(),
        ];
    }

    private static function validate(Crawler $crawler): void
    {
        $id = $crawler->filter('body > div')->attr('id');

        if (!\preg_match('[^page\d+$]', $id)) {
            throw new ParserException("Unexpected page type: \"$id\".", ParserException::UNEXPECTED_TYPE);
        }
    }

    private static function extractUserId(Crawler $crawler): int
    {
        $uid = (int)$crawler->filter('.apphub_friend_block')->attr('data-miniprofile');

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
        $date = $crawler->filter('.date_posted')->text();

        if (!StringType::startsWith($date, $prefix = 'Posted: ')) {
            throw new ParserException("Unexpected date: \"$date\".");
        }

        return new \DateTimeImmutable(substr($date, strlen($prefix)));
    }
}
