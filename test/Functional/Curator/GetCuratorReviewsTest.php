<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use Amp\Loop;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\GetCuratorReviews;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\RecommendationState;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use function Amp\Promise\wait;

/**
 * @see GetCuratorReviews
 */
final class GetCuratorReviewsTest extends CuratorTestCase
{
    public function testListCuratorReviews(): void
    {
        $response = wait(self::$porter->importOneAsync(new AsyncImportSpecification(new PutCuratorReview(
            self::$session,
            self::CURATOR_ID,
            new CuratorReview(
                $appId = 130,
                'foo',
                $state = RecommendationState::NOT_RECOMMENDED()
            )
        ))));

        self::assertIsArray($response);
        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);

        Loop::run(static function () use ($appId, $state): \Generator {
            $foundAppId = false;

            $reviews = self::$porter->importAsync(new AsyncImportSpecification(
                new GetCuratorReviews(self::$session, self::CURATOR_ID)
            ));

            while (yield $reviews->advance()) {
                $review = $reviews->getCurrent();

                self::assertArrayHasKey('appid', $review);
                self::assertIsInt($review['appid']);
                self::assertNotEmpty($review['appid']);

                self::assertArrayHasKey('app_name', $review);
                self::assertIsString($review['app_name']);
                self::assertNotEmpty($review['app_name']);

                self::assertArrayHasKey('recommendation', $review);
                self::assertIsArray($review['recommendation']);
                self::assertNotEmpty($recommendation = $review['recommendation']);

                if ($review['appid'] === $appId) {
                    $foundAppId = true;

                    self::assertSame($state->toInt(), $recommendation['recommendation_state']);
                }
            }

            self::assertTrue(isset($review), 'At least one review was imported.');
            self::assertTrue($foundAppId, 'Found the app we inserted.');
        });
    }
}
