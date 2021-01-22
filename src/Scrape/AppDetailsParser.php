<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

final class AppDetailsParser
{
    use StaticClass;

    public static function tryParseStorePage(string $html): array
    {
        try {
            return self::parseStorePage($html);
        } catch (\InvalidArgumentException $exception) {
            // Promote empty node list to recoverable exception type.
            if ($exception->getMessage() === 'The current node list is empty.') {
                throw new InvalidMarkupException($exception->getMessage(), $exception->getCode(), $exception);
            }

            throw $exception;
        }
    }

    private static function parseStorePage(string $html): array
    {
        $crawler = new Crawler($html);

        self::validate($crawler, $html);

        $name = self::parseAppName($crawler);
        $type = self::parseAppType($crawler);
        $genres = self::parseGenres($crawler);
        $tags = self::parseTags($crawler);
        $languages = self::parseLanguages($crawler);
        $vrx = self::parseVrExclusive($crawler);
        $free = self::parseFree($crawler);

        // Title area.
        $app_id = self::parseAppId($crawler);

        // Media area.
        $videos = self::parseVideoIds($crawler);

        // Header area.
        $blurb = ($snippet = $crawler->filter('.game_description_snippet'))->count() ? trim($snippet->text()) : null;

        // Reviews area.
        $reviewsArea = $crawler->filter('.user_reviews')->first();
        $release_date = self::parseReleaseDate($reviewsArea);
        $developers = iterator_to_array(self::parseDevelopers($reviewsArea));
        $publishers = iterator_to_array(self::parsePublishers($reviewsArea));

        // Tags area.
        $canonical_id = +$crawler->filter('[data-appid]')->attr('data-appid');

        // Purchase area.
        $purchaseArea = self::findPrimaryPurchaseArea($crawler);
        $price = self::parsePrice($purchaseArea);
        $discount_price = self::parseDiscountPrice($purchaseArea);
        $discount = self::parseDiscountPercentage($purchaseArea);

        // Reviews.
        $positiveReviews = $crawler->filter('[for=review_type_positive] > .user_reviews_count');
        $hasReviews = $positiveReviews->count() > 0;
        $positive_reviews = $hasReviews ? self::filterNumbers($positiveReviews->text()) : 0;
        $negative_reviews = $hasReviews ? self::filterNumbers(
            $crawler->filter('[for=review_type_negative] > .user_reviews_count')->text()
        ) : 0;
        $steam_reviews = $hasReviews ? self::filterNumbers(
            $crawler->filter('[for=purchase_type_steam] > .user_reviews_count')->text()
        ) : 0;

        // Platforms.
        $platforms = $purchaseArea->filter('.game_area_purchase_platform')->first();
        $windows = $platforms->filter('.win')->count() > 0;
        $linux = $platforms->filter('.linux')->count() > 0;
        $mac = $platforms->filter('.mac')->count() > 0;

        // VR platforms.
        $vrPlatforms = $crawler->filter(
            '.block_title.vrsupport ~ .game_area_details_specs > a.name[href*="vrsupport=10"]'
        )->each(static function (Crawler $crawler): string {
            return preg_replace('[.*vrsupport=(\d+).*]', '$1', $crawler->attr('href'));
        });
        $vive = in_array('101', $vrPlatforms, true);
        $occulus = in_array('102', $vrPlatforms, true);
        $wmr = in_array('104', $vrPlatforms, true);
        $valve_index = in_array('105', $vrPlatforms, true);

        return compact(
            'name',
            'type',
            'app_id',
            'canonical_id',
            'blurb',
            'release_date',
            'developers',
            'publishers',
            'genres',
            'tags',
            'languages',
            'price',
            'discount_price',
            'discount',
            'vrx',
            'free',
            'videos',
            'positive_reviews',
            'negative_reviews',
            'steam_reviews',
            'windows',
            'linux',
            'mac',
            'vive',
            'occulus',
            'wmr',
            'valve_index'
        );
    }

    private static function validate(Crawler $crawler, string $html): void
    {
        if (!$bodyClassesElement = $crawler->filter('body')->attr('class')) {
            throw new InvalidMarkupException('Cannot parse body tag\'s class attribute: ' . $html);
        }

        $bodyClasses = explode(' ', $bodyClassesElement);

        if (!\in_array('v6', $bodyClasses, true)) {
            throw new ParserException('Unexpected version! Expected: v6.', ParserException::UNEXPECTED_VERSION);
        }

        if (!\in_array('app', $bodyClasses, true)) {
            $error = $crawler->filter('#error_box .error');

            if ($error->count()) {
                throw new SteamStoreException($error->text());
            }

            throw new ParserException('Unexpected page type! Expected: app.', ParserException::UNEXPECTED_TYPE);
        }
    }

    private static function parseAppName(Crawler $crawler): string
    {
        return $crawler->filter('.apphub_AppName')->text();
    }

    private static function parseAppId(Crawler $crawler): int
    {
        if (preg_match('[/app/(\d+)$]', $crawler->filter('.apphub_OtherSiteInfo > a')->attr('href'), $matches)) {
            return +$matches[1];
        }

        throw new ParserException('Could not parse canonical app ID.', ParserException::MISSING_APP_ID);
    }

    private static function parseAppType(Crawler $crawler): string
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
            $release_date = $date->count() ? new \DateTimeImmutable($date->text(), new \DateTimeZone('UTC')) : null;
        } catch (\Exception $exception) {
            $release_date = null;
        }

        // If date includes time portion, assume it was mis-parsed.
        return $release_date && $release_date->format('G') ? null : $release_date;
    }

    /**
     * @return \Traversable[name => id]
     */
    private static function parseDevelopers(Crawler $crawler): \Traversable
    {
        foreach ($crawler->filter('#developers_list > a') as $a) {
            yield trim($a->nodeValue) => self::filterDevlisherUrl($a->attributes['href']->value);
        }
    }

    private static function parsePublishers(Crawler $crawler): \Traversable
    {
        foreach ($crawler->filter('.dev_row > .summary.column:not([id]) > a') as $a) {
            yield trim($a->nodeValue) => self::filterDevlisherUrl($a->attributes['href']->value);
        }
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
     * @param Crawler $purchaseArea
     *
     * @return int|null Price if integer, 0 if app is free and null if app has no price (i.e. not for sale).
     */
    private static function parsePrice(Crawler $purchaseArea): ?int
    {
        $priceElement = $purchaseArea->filter('.game_purchase_price');
        $discountElement = $purchaseArea->filter('.discount_original_price');

        if (\count($priceElement) || \count($discountElement)) {
            $price = self::trimNodeText(\count($priceElement) ? $priceElement : $discountElement);

            if (preg_match('[^\$\d+\.\d\d$]', $price)) {
                return self::filterNumbers($price);
            }
        }

        return $purchaseArea->filter('.game_purchase_action')->count() ? 0 : null;
    }

    private static function parseDiscountPrice(Crawler $purchaseArea): ?int
    {
        $discountPriceElement = $purchaseArea->filter('.discount_final_price');

        if (\count($discountPriceElement)) {
            return self::filterNumbers($discountPriceElement->text());
        }

        return null;
    }

    private static function parseDiscountPercentage(Crawler $purchaseArea): int
    {
        $element = $purchaseArea->filter('.discount_pct');

        return $element->count() ? self::filterNumbers($element->text()) : 0;
    }

    private static function parseVrExclusive(Crawler $crawler): bool
    {
        foreach ($crawler->filter('.notice_box_content') as $element) {
            if (preg_match('[Requires.+virtual reality headset]', $element->textContent)) {
                return true;
            }
        }

        return false;
    }

    private static function parseFree(Crawler $crawler): ?bool
    {
        $tooltip = $crawler->filter('#review_histogram_rollup_section .tooltip');

        if (!$tooltip->count()) {
            return null;
        }

        return (bool)preg_match('[\bfree\b]', $tooltip->attr('data-tooltip-text'));
    }

    private static function parseVideoIds(Crawler $crawler)
    {
        return $crawler->filter('#highlight_strip_scroll > .highlight_strip_movie')->each(
            static function (Crawler $crawler): int {
                return self::filterNumbers($crawler->attr('id'));
            }
        );
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
        return +preg_replace('[\D]', '', $input);
    }

    /**
     * Filters the specified developer/publisher URL so only the devlisher's identifier remains.
     *
     * @param string $url Developer/publisher URL.
     *
     * @return string Devlisher ID.
     */
    private static function filterDevlisherUrl(string $url): ?string
    {
        if (preg_match('[er/([^?]+)]', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Finds the "primary" purchase area, that is, the purchase area representing the main package for this app.
     *
     * @return Crawler Crawler containing the primary purchase area node if found, otherwise a crawler with no nodes.
     */
    private static function findPrimaryPurchaseArea(Crawler $crawler): Crawler
    {
        $purchaseArea = $crawler->filter(
            '#game_area_purchase .game_area_purchase_game:not(.demo_above_purchase)
                > .game_area_purchase_platform:not(:empty)'
        );

        return $purchaseArea->count() ? $purchaseArea->closest('.game_area_purchase_game') : $purchaseArea;
    }
}
