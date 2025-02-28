<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\RecommendationState;

/**
 * @see PutCuratorReview
 */
final class PutCuratorReviewTest extends CuratorTestCase
{
    public function testPutCuratorReview(): void
    {
        $response = self::$porter->importOne(new Import(new PutCuratorReview(
            self::$session,
            self::CURATOR_ID,
            new CuratorReview(
                60,
                'foo',
                RecommendationState::RECOMMENDED
            )
        )));

        self::assertIsArray($response);
        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);
        self::assertArrayHasKey('clanid', $response);
        self::assertSame(self::CURATOR_ID, $response['clanid']);
    }
}
