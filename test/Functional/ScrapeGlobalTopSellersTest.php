<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeGlobalTopSellers;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see ScrapeGlobalTopSellers
 */
final class ScrapeGlobalTopSellersTest extends TestCase
{
    /**
     * Tests that the global top sellers are paged through seamlessly and each record contains an app ID and a price
     * that is an integer or null. Paging stops after the configured maximum number of pages, the point at which Steam
     * begins rate-limiting.
     */
    public function testGlobalTopSellers(): void
    {
        $results = FixtureFactory::createPorter()->import(new Import(new ScrapeGlobalTopSellers()));

        $maxRecords = ScrapeGlobalTopSellers::MAX_PAGES * ScrapeGlobalTopSellers::MAX_RESULTS;

        $count = 0;
        foreach ($results as $result) {
            ++$count;

            self::assertArrayHasKey('appid', $result);
            self::assertIsInt($result['appid']);

            self::assertArrayHasKey('price', $result);
            self::assertThat(
                $result['price'],
                self::logicalOr(self::isInt(), self::isNull()),
                'Price must be an integer or null when unavailable.'
            );
        }

        self::assertSame($maxRecords, $count);
    }
}
