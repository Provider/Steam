<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use Amp\Loop;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\GetCuratorReviews;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\RecommendationState;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;

final class GetCuratorReviewsTest extends CuratorTestCase
{
    public function testListCuratorReviews(): void
    {
        $response = \Amp\Promise\wait(self::$porter->importOneAsync(new AsyncImportSpecification(new PutCuratorReview(
            self::$session,
            $curatorId = '31457321',
            $appId = '130',
            'foo',
            $state = RecommendationState::NOT_RECOMMENDED()
        ))));

        self::assertInternalType('array', $response);
        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);

        $reviews = self::$porter->importAsync(new AsyncImportSpecification(
            new GetCuratorReviews(self::$session, $curatorId)
        ));

        Loop::run(static function () use ($reviews, $appId, $state): \Generator {
            $foundAppId = false;

            while (yield $reviews->advance()) {
                $review = $reviews->getCurrent();

                self::assertArrayHasKey('appid', $review);
                self::assertInternalType('int', $review['appid']);
                self::assertNotEmpty($review['appid']);

                self::assertArrayHasKey('app_name', $review);
                self::assertInternalType('string', $review['app_name']);
                self::assertNotEmpty($review['app_name']);

                self::assertArrayHasKey('recommendation', $review);
                self::assertInternalType('array', $review['recommendation']);
                self::assertNotEmpty($recommendation = $review['recommendation']);

                if ($review['appid'] === (int)$appId) {
                    $foundAppId = true;

                    self::assertSame($state->toInt(), $recommendation['recommendation_state']);
                }
            }

            self::assertTrue(isset($review), 'At least one review was imported.');
            self::assertTrue($foundAppId, 'Found the app we inserted.');
        });
    }
}
