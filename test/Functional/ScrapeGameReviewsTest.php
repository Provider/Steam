<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Amp\PHPUnit\AsyncTestCase;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncGameReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeGameReviews;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see ScrapeGameReviews
 */
final class ScrapeGameReviewsTest extends AsyncTestCase
{
    private const REVIEWS_PER_PAGE = 10;

    private $porter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->porter = FixtureFactory::createPorter();
    }

    /**
     * @see https://store.steampowered.com/app/719070/BlowOut/
     */
    public function testOnePage(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeGameReviews(719070)))
            ->findFirstCollection();
        $total = yield $reviews->getTotal();
        $uids = [];

        while (yield $reviews->advance()) {
            $review = $reviews->getCurrent();

            self::assertIsArray($review);
            self::assertArrayHasKey('review_id', $review);
            self::assertArrayHasKey('user_id', $review);
            self::assertArrayHasKey('positive', $review);
            self::assertArrayHasKey('date', $review);

            self::assertNotContains($uid = $review['user_id'], $uids, 'Unique user_ids only.');
            $uids[] = $review['user_id'];
        }

        self::assertGreaterThan(0, $count = count($uids));
        self::assertLessThan(self::REVIEWS_PER_PAGE, $count);

        self::assertCount($total, $uids);
    }

    /**
     * Tests that an app with two review pages is parsed correctly.
     *
     * @see https://store.steampowered.com/app/614770/Beachhead_DESERT_WAR/
     */
    public function testTwoPages(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeGameReviews(614770)))
            ->findFirstCollection();
        $total = yield $reviews->getTotal();
        $uids = [];

        while (yield $reviews->advance()) {
            $review = $reviews->getCurrent();

            self::assertIsArray($review);
            self::assertArrayHasKey('review_id', $review);
            self::assertArrayHasKey('user_id', $review);
            self::assertArrayHasKey('positive', $review);
            self::assertArrayHasKey('date', $review);

            self::assertNotContains($uid = $review['user_id'], $uids, 'Unique user_ids only.');
            $uids[] = $review['user_id'];
        }

        self::assertGreaterThan(self::REVIEWS_PER_PAGE, $count = count($uids));
        self::assertLessThan(self::REVIEWS_PER_PAGE * 2, $count);

        self::assertCount($total, $uids);
    }

    /**
     * Tests that an app with multiple review pages is parsed correctly.
     *
     * @see https://store.steampowered.com/app/302160/The_Egyptian_Prophecy_The_Fate_of_Ramses/
     */
    public function testMultiplePages(): \Generator
    {
        /** @var AsyncGameReviewsRecords $reviews */
        $reviews = $this->porter->importAsync(new AsyncImportSpecification(new ScrapeGameReviews(302160)))
            ->findFirstCollection();
        $uids = [];

        while (yield $reviews->advance()) {
            $review = $reviews->getCurrent();

            self::assertIsArray($review);
            self::assertArrayHasKey('review_id', $review);
            self::assertArrayHasKey('user_id', $review);
            self::assertArrayHasKey('positive', $review);
            self::assertArrayHasKey('date', $review);

            self::assertNotContains($uid = $review['user_id'], $uids, 'Unique user_ids only.');
            $uids[] = $review['user_id'];
        }

        self::assertGreaterThan(self::REVIEWS_PER_PAGE * 8, $count = count($uids));

        self::assertCount(yield $reviews->getTotal(), $uids);
    }
}
