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
            new ScrapeUserReviews('http://steamcommunity.com/id/Fluo')
        ));

        $reviews = iterator_to_array($reviews, false);

        // Page size is 10.
        self::assertGreaterThan(10, \count($reviews));

        foreach ($reviews as $review) {
            self::assertArrayHasKey('app_id', $review);
            self::assertArrayHasKey('positive', $review);
            self::assertInternalType('int', $review['app_id']);
            self::assertInternalType('bool', $review['positive']);
        }
    }

    /**
     * Tests that a user with no reviews throws a parser exception.
     */
    public function testPrivateProfile(): void
    {
        $reviews = $this->porter->import(new ImportSpecification(
            new ScrapeUserReviews('http://steamcommunity.com/id/SteamTop250')
        ));

        $this->expectException(ParserException::class);
        $reviews->current();
    }
}
