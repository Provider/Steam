<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\Porter\Type\StringType;
use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

final class AppDetailsParser
{
    use StaticClass;

    public static function parseStorePage(string $html): array
    {
        $crawler = new Crawler($html);

        self::validate($crawler);

        $name = self::parseAppName($crawler);
        $type = self::parseAppType($crawler);
        $release_date = self::parseReleaseDate($crawler);
        $genres = self::parseGenres($crawler);
        $tags = self::parseTags($crawler);
        $languages = self::parseLanguages($crawler);
        $discount = self::parseDiscountPercentage($crawler);
        $is_free = self::parseIsFree($crawler);

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
        $vive = $crawler->filter('.game_area_purchase_platform')->first()->filter('.htcvive')->count() > 0;
        $occulus = $crawler->filter('.game_area_purchase_platform')->first()->filter('.oculusrift')->count() > 0;
        $wmr = $crawler->filter('.game_area_purchase_platform')->first()->filter('.windowsmr')->count() > 0;

        return compact(
            'name',
            'type',
            'release_date',
            'genres',
            'tags',
            'languages',
            'discount',
            'is_free',
            'positive_reviews',
            'negative_reviews',
            'windows',
            'linux',
            'mac',
            'vive',
            'occulus',
            'wmr'
        );
    }

    private static function validate(Crawler $crawler): void
    {
        $bodyClasses = explode(' ', $crawler->filter('body')->attr('class'));

        if (!\in_array('v6', $bodyClasses, true)) {
            throw new ParserException('Unexpected version! Expected: v6.');
        }

        if (!\in_array('app', $bodyClasses, true)) {
            throw new ParserException('Unexpected page type! Expected: app.');
        }
    }

    private static function parseAppName(Crawler $crawler): string
    {
        return $crawler->filter('.apphub_AppName')->text();
    }

    private static function parseAppType(Crawler $crawler)
    {
        return mb_strtolower(
            preg_replace(
                '[.*Is this (\S+)\b.*]',
                '$1',
                $crawler->filter('.responsive_apppage_details_right.heading')->text()
            )
        );
    }

    private static function parseReleaseDate(Crawler $crawler): ?\DateTimeImmutable
    {
        $date = $crawler->filter('.release_date > .date');

        try {
            $release_date = $date->count() ? new \DateTimeImmutable($date->text()) : null;
        } catch (\Exception $exception) {
            $release_date = null;
        }

        return $release_date;
    }

    private static function parseTags(Crawler $crawler): array
    {
        return $crawler->filter('.app_tag:not(.add_button)')->each(
            \Closure::fromCallable('self::trimNodeText')
        );
    }

    private static function parseGenres(Crawler $crawler): array
    {
        return $crawler->filter('.details_block a[href*="/genre/"]')->each(
            \Closure::fromCallable('self::trimNodeText')
        );
    }

    private static function parseLanguages(Crawler $crawler): array
    {
        return $crawler->filter('.game_language_options tr:not(.unsupported) > td:first-child')->each(
            \Closure::fromCallable('self::trimNodeText')
        );
    }

    private static function parseDiscountPercentage(Crawler $crawler): int
    {
        $element = $crawler->filter('.game_area_purchase_game')->first()->filter('.discount_pct');

        return $element->count() ? self::filterNumbers($element->text()) : 0;
    }

    private static function parseIsFree(Crawler $crawler): bool
    {
        $element = $crawler->filter('.game_area_purchase_game')->first()->filter('.game_purchase_price');

        // Assume games without a purchase price are free.
        if (!\count($element)) {
            return true;
        };

        return StringType::startsWith(self::trimNodeText($element), 'Free');
    }

    private static function trimNodeText(Crawler $crawler): string
    {
        return trim($crawler->text());
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
