<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

final class AppDetailsParser
{
    use StaticClass;

    public static function parseStorePage(string $html): array
    {
        $crawler = new Crawler($html);

        // Basic validation.
        $bodyClasses = explode(' ', $crawler->filter('body')->attr('class'));
        if (!in_array('v6', $bodyClasses, true)) {
            throw new ParserException('Unexpected version! Expected: v6.');
        }
        if (!in_array('app', $bodyClasses, true)) {
            throw new ParserException('Unexpected content! Expected: app.');
        }

        // App name.
        $name = $crawler->filter('.apphub_AppName')->text();

        // App type.
        $type = mb_strtolower(
            preg_replace(
                '[.*Is this (\S+)\b.*]',
                '$1',
                $crawler->filter('.responsive_apppage_details_right.heading')->text()
            )
        );

        // Release date.
        $date = $crawler->filter('.release_date > .date');
        try {
            $release_date = $date->count() ? new \DateTimeImmutable($date->text()) : null;
        } catch (\Exception $exception) {
            $release_date = null;
        }

        // Tags.
        $tags = $crawler->filter('.app_tag:not(.add_button)')->each(function (Crawler $node): string {
            return trim($node->text());
        });

        // Reviews.
        $positiveReviews = $crawler->filter('[for=review_type_positive] > .user_reviews_count');
        $hasReviews = $positiveReviews->count() > 0;
        $positive_reviews = $hasReviews ? self::filterNumbers($positiveReviews->text()) : 0;
        $negative_reviews = $hasReviews ? self::filterNumbers(
            $crawler->filter('[for=review_type_negative] > .user_reviews_count')->text()
        ) : 0;

        // Platforms.
        $windows = $crawler->filter('.game_area_purchase_platform')->first()->filter('.win')->count() > 0;
        $linux = $crawler->filter('.game_area_purchase_platform')->first()->filter('.linux')->count() > 0;
        $mac = $crawler->filter('.game_area_purchase_platform')->first()->filter('.mac')->count() > 0;

        return compact(
            'name',
            'type',
            'release_date',
            'tags',
            'positive_reviews',
            'negative_reviews',
            'windows',
            'linux',
            'mac'
        );
    }

    /**
     * Filters the specified input string so only numbers remain and casts to numeric.
     *
     * @param string $input Input string.
     *
     * @return int Filtered string as numeric value.
     */
    private static function filterNumbers(string $input): int
    {
        return +preg_replace('[\D]', null, $input);
    }
}
