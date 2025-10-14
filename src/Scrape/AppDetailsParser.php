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
        $html = self::sanitize($html);
        $crawler = new NativeCrawler($html);

        self::validate($crawler, $html);

        $name = self::parseAppName($crawler);
        $type = self::parseAppType($crawler);
        $genres = self::parseGenres($crawler);
        $tags = self::parseTags($crawler);
        $languages = self::parseLanguages($crawler);
        $vrx = self::parseVrExclusive($crawler);
        $adult = self::parseAdult($crawler);
        $capsule_url = self::parseCapsuleUrl($crawler);
        $main_capsule_url = self::parseMainCapsuleUrl($crawler);

        // Title area.
        $app_id = self::parseAppId($crawler);

        // Media area.
        $videos = self::parseVideoThumbnails($crawler);

        // Header area.
        $blurb = ($snippet = $crawler->filter('.game_description_snippet'))->count() ? trim($snippet->text()) : null;

        // Reviews area.
        $reviewsArea = $crawler->filter('.glance_ctn_responsive_left')->first();
        $release_date = self::parseReleaseDate($reviewsArea);
        $developers = iterator_to_array(self::parseDevelopers($reviewsArea));
        $publishers = iterator_to_array(self::parsePublishers($reviewsArea));

        // Tags area.
        $canonical_id = ($appId = $crawler->filter('[data-appid]'))->count() ? +$appId->attr('data-appid') : null;

        // Purchase area.
        [$purchaseArea, $DEBUG_primary_sub_id] = self::findPrimaryPurchaseArea($crawler, $name, $canonical_id);
        $bundle_id = self::parseBundleId($purchaseArea);
        $free = self::parseFree($purchaseArea);
        $price = $free ? 0 : ($bundle_id ? self::calculateBundlePrice($purchaseArea) : self::parsePrice($purchaseArea));
        $discount_price = $free ? null : self::parseDiscountPrice($purchaseArea);
        $discount = $free ? 0 : self::parseDiscountPercentage($purchaseArea);

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

        // Steam Deck.
        $steam_deck = self::parseSteamDeckCompatibility($crawler);

        // Demo.
        $demo_id = self::parserDemoId($crawler);

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
            'adult',
            'videos',
            'positive_reviews',
            'negative_reviews',
            'steam_reviews',
            'windows',
            'linux',
            'mac',
            'steam_deck',
            'demo_id',
            'bundle_id',
            'capsule_url',
            'main_capsule_url',
            'DEBUG_primary_sub_id',
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
        $type = mb_strtolower(
            preg_replace(
                '[^All (\w+?)s?$]',
                '$1',
                $crawler->filter('.breadcrumbs a:first-of-type')->text()
            )
        );

        if ($type === 'game') {
            if ($crawler->filter('.category_icon[src$="/ico_dlc.png"]')->count()) {
                return 'dlc';
            }

            if ($crawler->filter('.category_icon[src$="/ico_demo.gif"]')->count()) {
                return 'demo';
            }

            if ($crawler->filter('.game_area_mod_bubble')->count()) {
                return 'mod';
            }
        }

        if ($type === 'video' && $crawler->filter('.series_seasons:not(.extra_content_spacer)')->count()) {
            return 'series';
        }

        return $type;
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
            self::trimNodeText(...)
        );
    }

    private static function parseLanguages(Crawler $crawler): array
    {
        return $crawler->filter('.game_language_options tr:not(.unsupported) > td:first-child')->each(
            self::trimNodeText(...)
        );
    }

    /**
     * Parses the price in the specified purchase area.
     *
     * The price is the regular purchase price when not discounted, or the original price when discounted.
     *
     * @param Crawler $purchaseArea Purchase area.
     *
     * @return int|null Price if integer, 0 if app is free and null if app has no price (i.e. not for sale).
     */
    private static function parsePrice(Crawler $purchaseArea): ?int
    {
        // Not present when discounted.
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
        $element = $purchaseArea->filter('.discount_pct, .bundle_base_discount');

        return $element->count() ? self::filterNumbers($element->text()) : 0;
    }

    private static function parseBundleId(Crawler $purchaseArea): ?int
    {
        if (!($input = $purchaseArea->filter('input[name=bundleid]'))->count()) {
            return null;
        }

        return +$input->attr('value');
    }

    private static function calculateBundlePrice(Crawler $purchaseArea): int
    {
        $data = $purchaseArea->getNode(0)->parentNode->getAttribute('data-ds-bundle-data');
        $json = json_decode($data, true, flags: JSON_THROW_ON_ERROR);

        return array_reduce(
            $json['m_rgItems'],
            static fn (int $acc, array $item) => $acc + $item['m_nBasePriceInCents'],
            0
        );
    }

    private static function parseVrExclusive(Crawler $crawler): bool
    {
        return $crawler->filter('[data-featuretarget=game-notice-vr-required]')->count() > 0;
    }

    private static function parseFree(Crawler $crawler): bool
    {
        if ($crawler->count()) {
            if (str_contains($crawler->attr('aria-labelledby') ?? '', '_free_')) {
                return true;
            }

            if (($form = $crawler->filter('form'))->count()) {
                return str_contains($form->attr('action'), '/addfreelicense/');
            }
        }

        return false;
    }

    private static function parseAdult(Crawler $crawler): bool
    {
        return $crawler->filter('.mature_content_notice')->count() === 1;
    }

    private static function parseVideoThumbnails(Crawler $crawler): array
    {
        return $crawler->filter('#highlight_player_area .highlight_movie[data-props]')->each(
            static fn (Crawler $crawler) => json_decode($crawler->attr('data-props'), true, flags: JSON_THROW_ON_ERROR)
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
     * Finds the "primary" purchase area within the specified crawler instance, that is, the purchase area representing
     * the main package for this app. When applicable, the app title will be compared against game purchase areas to
     * find the best match.
     *
     * @param Crawler $crawler Crawler instance.
     * @param string $title App title.
     * @param ?int $appId Optional. App ID. May be null when parsing a hardware app.
     *
     * @return (Crawler|?int)[] [
     *     Crawler containing the primary purchase area node if found, otherwise a crawler with no nodes.,
     *     Primary sub ID.,
     * ]
     */
    private static function findPrimaryPurchaseArea(Crawler $crawler, string $title, ?int $appId): array
    {
        // Detect if game is free.
        if ($appId &&
            count($purchaseArea = $crawler->filter("[aria-labelledby=game_area_purchase_section_free_$appId]"))) {
            return [$purchaseArea, null];
        }

        // Detect if game is multi-sub.
        if (count($purchaseAreas = self::filterPurchaseAreas($crawler))) {
            // Collect purchase area titles.
            foreach ($purchaseAreas as $purchaseArea) {
                if (!$subId = self::findPurchaseAreaSubId($purchaseArea = new NativeCrawler($purchaseArea))) {
                    continue;
                }

                // Use sub if title matches exactly.
                if (($titles[$subId] = $purchaseArea->filter('.title')->text()) === "Buy $title") {
                    return [$purchaseArea, $subId];
                }
            }

            if (isset($titles)) {
                // If there is exactly one non-package, pick it.
                if (count($nonPackage = self::filterNonPackages($purchaseAreas)) === 1) {
                    return [$nonPackage, self::findPurchaseAreaSubId($nonPackage)];
                }

                // Count how many purchase areas contain product title.
                if (count(array_filter($titles, static fn (string $t) => str_contains($t, $title))) > 1) {
                    // If more than one, use purchase area with the lowest sub ID.
                    ksort($titles);
                }

                // Use first purchase area regardless of whether that area actually contains the title.
                // This is mostly applicable to non-game apps.
                return [
                    self::findPurchaseAreaBySubId($purchaseAreas, key($titles)),
                    key($titles),
                ];
            }
        }

        // Pick first purchase area with platforms defined that is not a demo area.
        return [$purchaseAreas->eq(0), null];
    }

    private static function filterPurchaseAreas(Crawler $crawler): Crawler
    {
        // Pick purchase areas with platform icons.
        // TODO: Use :has when supported, since there may be multiple platform_img elements, duplicating work.
        //     https://github.com/symfony/symfony/pull/49388
        $purchaseAreas = $crawler->filter(
            '#game_area_purchase .game_area_purchase_game:not(.demo_above_purchase)
                > .game_area_purchase_platform > .platform_img'
        );

        if (!$purchaseAreas->count()) {
            // Empty crawler.
            return $purchaseAreas;
        }

        return new NativeCrawler($purchaseAreas->each(
            static fn (Crawler $crawler) => $crawler->closest('.game_area_purchase_game')->getNode(0)
        ));
    }

    private static function filterNonPackages(Crawler $purchaseAreas): Crawler
    {
        return $purchaseAreas->reduce(fn (Crawler $crawler) => !$crawler->filter('.btn_packageinfo')->count());
    }

    private static function findPurchaseAreaSubId(Crawler $crawler): ?int
    {
        if (count($subId = $crawler->filter('input[name=subid]'))) {
            return (int)$subId->attr('value') ?: null;
        }

        return null;
    }

    private static function findPurchaseAreaBySubId(Crawler $crawler, int $subId): Crawler
    {
        return $crawler->filter("#game_area_purchase_section_add_to_cart_$subId");
    }

    private static function parseSteamDeckCompatibility(Crawler $crawler): ?SteamDeckCompatibility
    {
        $config = $crawler->filter('#application_config');

        if (count($config) && $deckCompatJson = $config->attr('data-deckcompatibility')) {
            $deckCompat = \json_decode($deckCompatJson, true, 512, JSON_THROW_ON_ERROR);

            return SteamDeckCompatibility::fromId($deckCompat['resolved_category']);
        }

        return null;
    }

    private static function parserDemoId(Crawler $crawler): ?int
    {
        // Type 1: demo button in purchase area, type 2: demo button in sidebar.
        $demoLink = $crawler->filter('#demoGameBtn > a, .download_demo_button a');

        if (count($demoLink) && $demoId = preg_replace('[.*?steam://install/(\d+).*]', '$1', $demoLink->attr('href'))) {
            return +$demoId;
        }

        return null;
    }

    private static function parseCapsuleUrl(Crawler $crawler): string
    {
        return $crawler->filter('meta[itemprop=image]')->attr('content');
    }

    private static function parseMainCapsuleUrl(Crawler $crawler): string
    {
        return ($src = $crawler->filter('link[rel=image_src]'))->count() ? $src->attr('href') : '';
    }

    /**
     * Strips the script containing the ShowComparisonDialog function from the specified HTML source. This JS function
     * includes quoted markup, which breaks the parser on pages where games are sold in "editions". This should not
     * be necessary once a proper native parser is implemented.
     *
     * @see https://github.com/symfony/symfony/pull/54383
     */
    private static function sanitize(string $html): string
    {
        return preg_replace('[<script>(.(?!</script>))*function ShowComparisonDialog.*?</script>]s', '', $html);
    }
}
