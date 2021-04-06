<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Amp\PHPUnit\AsyncTestCase;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncGameReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeAppReviews;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see ScrapeAppReviews
 */
final class ScrapeAppReviewsTest extends AsyncTestCase
{
    private const REVIEWS_PER_PAGE = 20;

    private $porter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->porter = FixtureFactory::createPorter();
    }

    /**
     * @link https://store.steampowered.com/app/256611/CSX_SD70MAC_Addon_Livery/
     */
    public function testZeroReviews(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeAppReviews(256611)))
            ->findFirstCollection();

        self::assertSame(0, yield $reviews->getTotal());
        self::assertFalse(yield $reviews->advance(), 'No results.');
    }

    /**
     * @link https://store.steampowered.com/app/719070/BlowOut/
     */
    public function testOnePage(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeAppReviews(719070)))
            ->findFirstCollection();
        $total = yield $reviews->getTotal();
        $uids = [];

        while (yield $reviews->advance()) {
            self::assertLooksLikeReview($review = $reviews->getCurrent());

            self::assertNotContains($uid = $review['user_id'], $uids, 'Unique user_ids only.');
            $uids[] = $review['user_id'];
        }

        self::assertGreaterThan(0, $count = count($uids));
        self::assertLessThanOrEqual(self::REVIEWS_PER_PAGE, $count);

        self::assertCount($total, $uids);
    }

    /**
     * Tests that an app with two review pages is parsed correctly.
     *
     * @link https://store.steampowered.com/app/347270/Knights_of_the_Sky/
     */
    public function testTwoPages(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeAppReviews(347270)))
            ->findFirstCollection();
        $total = yield $reviews->getTotal();
        $uids = [];

        while (yield $reviews->advance()) {
            self::assertLooksLikeReview($review = $reviews->getCurrent());

            self::assertNotContains($uid = $review['user_id'], $uids, 'Unique user_ids only.');
            $uids[] = $review['user_id'];
        }

        self::assertGreaterThan(self::REVIEWS_PER_PAGE, $count = count($uids));
        self::assertLessThanOrEqual(self::REVIEWS_PER_PAGE * 2, $count);

        self::assertCount($total, $uids);
    }

    /**
     * Tests that an app with multiple review pages is parsed correctly.
     *
     * @link https://store.steampowered.com/app/302160/The_Egyptian_Prophecy_The_Fate_of_Ramses/
     */
    public function testMultiplePages(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeAppReviews(302160)))
            ->findFirstCollection();
        $uids = [];

        while (yield $reviews->advance()) {
            self::assertLooksLikeReview($review = $reviews->getCurrent());

            self::assertNotContains($uid = $review['user_id'], $uids, 'Unique user_ids only.');
            $uids[] = $review['user_id'];
        }

        self::assertGreaterThan(self::REVIEWS_PER_PAGE * 4, $count = count($uids));

        self::assertCount(yield $reviews->getTotal(), $uids);
    }

    /**
     * Tests that an app with multiple reviews can be narrowed down to a single one with an appropriate date range.
     */
    public function testDateRange(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(
            new ScrapeAppReviews(302160, new \DateTimeImmutable('2014-07-01'), new \DateTimeImmutable('2014-07-02'))
        ))->findFirstCollection();

        self::assertSame(1, yield $reviews->getTotal());

        self::assertTrue(yield $reviews->advance(), 'Has one review.');
        self::assertLooksLikeReview($reviews->getCurrent());
        self::assertFalse(yield $reviews->advance());
    }

    /**
     * Tests that an app with a large total number of review is parsed successfully.
     *
     * @link https://store.steampowered.com/app/730/CounterStrike_Global_Offensive/
     */
    public function testLargeTotal(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(
            new ScrapeAppReviews(730)
        ))->findFirstCollection();

        self::assertGreaterThan(3800000, yield $reviews->getTotal());
        self::assertTrue(yield $reviews->advance(), 'Has results.');
    }

    /**
     * Tests that an app that redirects instead of emitting reviews throws an appropriate exception.
     *
     * On October 14th, 2020, Valve pushed an update that changed the response for reviews for invalid apps from
     * unsuccessful to successful but with no results. Generally this exception is no longer thrown unless the ID
     * is totally invalid, i.e. out of range.
     */
    public function testInvalidAppId(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(
            new ScrapeAppReviews(0)
        ));

        $this->expectException(InvalidAppIdException::class);

        yield $reviews->advance();
    }

    /**
     * Tests that an adult game, that is typically behind a login wall, is parsed successfully.
     */
    public function testAdultGame(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(
            new ScrapeAppReviews(1296770)
        ));

        self::assertTrue(yield $reviews->advance(), 'Has results.');
        self::assertLooksLikeReview($review = $reviews->getCurrent());
    }

    private static function assertLooksLikeReview(array $review): void
    {
        self::assertArrayHasKey('review_id', $review);
        self::assertIsInt($review['review_id']);
        self::assertArrayHasKey('user_id', $review);
        self::assertIsInt($review['user_id']);
        self::assertArrayHasKey('positive', $review);
        self::assertIsBool($review['positive']);
        self::assertArrayHasKey('source', $review);
        self::assertArrayHasKey('review_playtime', $review);

        self::assertArrayHasKey('date', $review);
        /** @var \DateTimeImmutable $date */
        self::assertInstanceOf(\DateTimeImmutable::class, $date = $review['date']);
        self::assertSame('000000', $date->format('His'), 'Date has no time component.');
        self::assertLessThan(new \DateTime(), $date, 'Date must be in the past.');
        self::assertGreaterThan(
            new \DateTime('2013-01'),
            $date,
            'Date must be after 2013, when reviews were released.'
        );
    }
}
