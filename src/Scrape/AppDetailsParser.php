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

        self::validate($crawler);

        $name = self::parseAppName($crawler);
        $type = self::parseAppType($crawler);
        $release_date = self::parseReleaseDate($crawler);
        $genres = self::parseGenres($crawler);
        $tags = self::parseTags($crawler);
        $languages = self::parseLanguages($crawler);
        $price = self::parsePrice($crawler);
        $discount_price = self::parseDiscountPrice($crawler);
        $discount = self::parseDiscountPercentage($crawler);

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
            'price',
            'discount_price',
            'discount',
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
        if (preg_match('[InitAppTagModal\(\h*\d+,\s*([^\v]+),\v]', $crawler->html(), $matches)) {
            return \json_decode($matches[1], true);
        }

        return [];
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

    /**
     * @param Crawler $crawler
     *
     * @return int|null Price if integer, 0 if app is free and null if app has no price (i.e. not for sale).
     */
    private static function parsePrice(Crawler $crawler): ?int
    {
        $purchaseArea = $crawler->filter('.game_area_purchase_game')->first();
        $priceElement = $purchaseArea->filter('.game_purchase_price');
        $discountElement = $purchaseArea->filter('.discount_original_price');

        if (\count($priceElement) || \count($discountElement)) {
            $price = self::trimNodeText(\count($priceElement) ? $priceElement: $discountElement);

            if (preg_match('[^\$\d+\.\d\d$]', $price)) {
                return self::filterNumbers($price);
            }
        }

        return $purchaseArea->filter('.game_purchase_action')->count() ? 0 : null;
    }

    private static function parseDiscountPrice(Crawler $crawler): ?int
    {
        $purchaseArea = $crawler->filter('.game_area_purchase_game')->first();
        $discountPriceElement = $purchaseArea->filter('.discount_final_price');

        if (\count($discountPriceElement)) {
            return self::filterNumbers($discountPriceElement->text());
        }

        return null;
    }

    private static function parseDiscountPercentage(Crawler $crawler): int
    {
        $element = $crawler->filter('.game_area_purchase_game')->first()->filter('.discount_pct');

        return $element->count() ? self::filterNumbers($element->text()) : 0;
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
