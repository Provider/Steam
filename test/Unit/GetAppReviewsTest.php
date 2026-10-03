<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Resource\AppReviewFilter;
use ScriptFUSION\Porter\Provider\Steam\Resource\AppReviewPurchaseType;
use ScriptFUSION\Porter\Provider\Steam\Resource\AppReviewType;
use ScriptFUSION\Porter\Provider\Steam\Resource\GetAppReviews;

/**
 * @see GetAppReviews
 */
final class GetAppReviewsTest extends TestCase
{
    public function testDefaultUrl(): void
    {
        $query = self::getQuery(new GetAppReviews(10));

        self::assertSame('10', $query['appid']);
        self::assertSame('0', $query['filter']);
        self::assertSame(['all'], $query['languages']);
        self::assertSame('0', $query['review_type']);
        self::assertSame('1', $query['purchase_type']);
        self::assertSame('20', $query['num_per_page']);
        self::assertArrayNotHasKey('day_range', $query);
    }

    public function testCustomUrl(): void
    {
        $query = self::getQuery(new GetAppReviews(
            440,
            AppReviewFilter::Helpful,
            'english',
            30,
            AppReviewType::All,
            AppReviewPurchaseType::All,
            100,
        ));

        self::assertSame('440', $query['appid']);
        self::assertSame('0', $query['filter']);
        self::assertSame(['english'], $query['languages']);
        self::assertSame('30', $query['day_range']);
        self::assertSame('0', $query['review_type']);
        self::assertSame('1', $query['purchase_type']);
        self::assertSame('100', $query['num_per_page']);
    }

    public function testMultipleLanguages(): void
    {
        $query = self::getQuery(new GetAppReviews(10, languages: ['english', 'french']));

        self::assertSame(['english', 'french'], array_values($query['languages']));
    }

    private static function getQuery(GetAppReviews $resource): array
    {
        parse_str(parse_url($resource->getUrl(), PHP_URL_QUERY), $query);

        return $query;
    }
}
