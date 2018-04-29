<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use Amp\Loop;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\ListCuratorReviews;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

final class ListCuratorReviewsTest extends TestCase
{
    public function testListCuratorReviews(): void
    {
        $porter = FixtureFactory::createPorter();

        $session = \Amp\Promise\wait(
            CuratorSession::create($porter, $_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])
        );

        $response = \Amp\Promise\wait($porter->importOneAsync(new AsyncImportSpecification(new PutCuratorReview(
            $session,
            $curatorId = '31457321',
            $appId = '130',
            'foo'
        ))));

        self::assertInternalType('array', $response);
        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);

        $reviews = $porter->importAsync(new AsyncImportSpecification(new ListCuratorReviews($session, $curatorId)));

        Loop::run(static function () use ($reviews, $appId): \Generator {
            $foundAppId = false;

            while (yield $reviews->advance()) {
                $review = $reviews->getCurrent();

                self::assertArrayHasKey('appid', $review);
                self::assertInternalType('int', $review['appid']);
                self::assertNotEmpty($review['appid']);

                if ($review['appid'] === (int)$appId) {
                    $foundAppId = true;
                }

                self::assertArrayHasKey('app_name', $review);
                self::assertInternalType('string', $review['app_name']);
                self::assertNotEmpty($review['app_name']);

                self::assertArrayHasKey('recommendation', $review);
                self::assertInternalType('array', $review['recommendation']);
                self::assertNotEmpty($review['recommendation']);
            }

            self::assertTrue(isset($review), 'At least one review was imported.');
            self::assertTrue($foundAppId, 'Found the app we inserted.');
        });
    }
}
