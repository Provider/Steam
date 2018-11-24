<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\RecommendationState;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;

/**
 * @see PutCuratorReview
 */
final class PutCuratorReviewTest extends CuratorTestCase
{
    public function testPutCuratorReview(): void
    {
        $response = \Amp\Promise\wait(self::$porter->importOneAsync(new AsyncImportSpecification(new PutCuratorReview(
            self::$session,
            $curatorId = '31457321',
            '60',
            'foo',
            RecommendationState::RECOMMENDED(),
            'http://example.com'
        ))));

        self::assertInternalType('array', $response);
        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);
        self::assertArrayHasKey('clanid', $response);
        self::assertSame($curatorId, (string)$response['clanid']);
    }
}
