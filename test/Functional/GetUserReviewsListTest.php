<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Collection\UserReviewsCollection;
use ScriptFUSION\Porter\Provider\Steam\Resource\GetUserReviewsList;
use ScriptFUSION\Porter\Specification\ImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see GetUserReviewsList
 */
final class GetUserReviewsListTest extends TestCase
{
    /**
     * Tests that when downloading reviews for game #10 (Counter-Strike), review totals add up.
     */
    public function testTotals()
    {
        /** @var UserReviewsCollection $reviews */
        $reviews = FixtureFactory::createPorter()->import(
            new ImportSpecification(new GetUserReviewsList(10))
        )->findFirstCollection();

        self::assertInstanceOf(UserReviewsCollection::class, $reviews);
        self::assertCount($reviews->getTotalPositive() + $reviews->getTotalNegative(), $reviews);

        return $reviews;
    }

    /**
     * Tests that when reviews have been downloaded for game #10, essential fields are present for each review.
     *
     * @depends testTotals
     */
    public function testReviewFields(UserReviewsCollection $reviews)
    {
        foreach ($reviews as $review) {
            self::assertInternalType('array', $review);
            self::assertArrayHasKey('author', $review);
            self::assertArrayHasKey('review', $review);
        }
    }

    /**
     * Tests that reviews for free games are included.
     */
    public function testFreeGameReviews()
    {
        /** @var UserReviewsCollection $reviews */
        $reviews = FixtureFactory::createPorter()->import(
            new ImportSpecification(new GetUserReviewsList(698780))
        )->findFirstCollection();

        self::assertInstanceOf(UserReviewsCollection::class, $reviews);
        self::assertGreaterThan(17000, count($reviews));
    }
}
