<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Symfony\Component\DomCrawler\Crawler;

final class StoreSearchParser
{
    public static function parse(Crawler $row): array
    {
        $priceNode = $row->filter('.search_price_discount_combined');

        // A present price node with `data-price-final` is always an integer: 0 for free games. When the node or the
        // attribute is absent, the price is unavailable (i.e. unreleased), represented as `null`.
        $price = $priceNode->count() ? $priceNode->attr('data-price-final') : null;

        return [
            'app_id' => (int)$row->attr('data-ds-appid'),
            'price' => $price === null ? null : (int)$price,
        ];
    }
}
