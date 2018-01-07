<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeUserReviews;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSION\Porter\Specification\ImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

final class ScrapeUserReviewsTest extends TestCase
{
    /**
     * @var Porter
     */
    private $porter;

    protected function setUp(): void
    {
        $this->porter = FixtureFactory::createPorter();
    }

    /**
     * Tests that a paginated list of reviews are retrieved in their entirety.
     */
    public function testPaginatedReviews(): void
    {
        $reviews = $this->porter->import(new ImportSpecification(
            new ScrapeUserReviews('http://steamcommunity.com/id/afarnsworth')
        ));

        // Page size is 10.
        self::assertGreaterThan(10, \count($reviews));

        $count = 0;
        foreach ($reviews as $review) {
            self::assertArrayHasKey('app_id', $review);
            self::assertArrayHasKey('url', $review);
            self::assertArrayHasKey('positive', $review);

            self::assertInternalType('int', $review['app_id']);
            self::assertInternalType('string', $review['url']);
            self::assertInternalType('bool', $review['positive']);

            ++$count;
        }

        self::assertCount($count, $reviews);
    }

    /**
     * Tests that a user with no reviews throws a parser exception.
     */
    public function testPrivateProfile(): void
    {
        $this->expectException(ParserException::class);

        $this->porter->import(new ImportSpecification(
            new ScrapeUserReviews('http://steamcommunity.com/id/SteamTop250')
        ));
    }
}
