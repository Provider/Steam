<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\DeleteCuratorReviews;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\GetCuratorReviews;
use ScriptFUSION\Porter\Transform\FilterTransformer;

final class DeleteCuratorReviewsTest extends CuratorTestCase
{
    public function testDeleteCuratorReviews(): void
    {
        self::createReview($app1 = 583950); // Artifact.
        self::createReview($app2 = 546560); // Half-Life: Alyx.

        $reviews = $this->fetchReviews();
        self::assertContains($app1, $reviews);
        self::assertContains($app2, $reviews);

        $response = self::$porter->importOne(
            new Import(new DeleteCuratorReviews(self::$session, self::CURATOR_ID, [$app1, $app2]))
        );
        self::assertSame(1, $response['success']);

        $reviews = $this->fetchReviews();

        self::assertNotContains($app1, $reviews);
        self::assertNotContains($app2, $reviews);
    }

    private function fetchReviews(): array
    {
        return iterator_to_array(\iter\map(
            fn (array $review) => $review['appid'],
            self::$porter->import((new Import(new GetCuratorReviews(self::$session, self::CURATOR_ID)))->addTransformer(
                new FilterTransformer(fn(array $review) => $review['recommendation']['recommendation_state'] === 2)
            ))
        ));
    }
}
