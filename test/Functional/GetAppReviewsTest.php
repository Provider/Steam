<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Collection\UserReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\Resource\AppReviewFilter;
use ScriptFUSION\Porter\Provider\Steam\Resource\AppReviewPurchaseType;
use ScriptFUSION\Porter\Provider\Steam\Resource\AppReviewType;
use ScriptFUSION\Porter\Provider\Steam\Resource\GetAppReviews;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see GetAppReviews
 */
final class GetAppReviewsTest extends TestCase
{
    /**
     * Tests that when downloading reviews for game #10 (Counter-Strike), review totals add up.
     */
    public function testTotals(): UserReviewsRecords
    {
        /** @var UserReviewsRecords $reviews */
        $reviews = FixtureFactory::createPorter()->import(
            new Import(new GetAppReviews(10))
        )->findFirstCollection();

        self::assertInstanceOf(UserReviewsRecords::class, $reviews);
        self::assertCount($reviews->getTotalPositive() + $reviews->getTotalNegative(), $reviews);

        return $reviews;
    }

    /**
     * Tests that when reviews have been downloaded for game #10, essential fields are present for each review.
     */
    #[Depends('testTotals')]
    public function testReviewFields(UserReviewsRecords $reviews): void
    {
        foreach ($reviews as $review) {
            self::assertIsArray($review);
            self::assertArrayHasKey('author', $review);
            self::assertArrayHasKey('review', $review);
        }
    }

    /**
     * Tests that reviews for free games are included.
     */
    public function testFreeGameReviews(): void
    {
        /** @var UserReviewsRecords $reviews */
        $reviews = FixtureFactory::createPorter()->import(
            new Import(new GetAppReviews(698780))
        )->findFirstCollection();

        self::assertInstanceOf(UserReviewsRecords::class, $reviews);
        self::assertGreaterThan(17000, count($reviews));
    }

    /**
     * Tests that language, day range and page size parameters are honored.
     */
    public function testCustomParameters(): void
    {
        /** @var UserReviewsRecords $reviews */
        $reviews = FixtureFactory::createPorter()->import(
            new Import(new GetAppReviews(
                10,
                AppReviewFilter::Helpful,
                'english',
                30,
                AppReviewType::All,
                AppReviewPurchaseType::All,
                5,
            ))
        )->findFirstCollection();

        $page = [...$reviews];

        self::assertCount(5, $page);

        foreach ($page as $review) {
            self::assertSame('english', $review['language']);
        }
    }
}
