<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\RecommendationState;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

abstract class CuratorTestCase extends TestCase
{
    protected const CURATOR_ID = 31457321;

    protected static Porter $porter;

    protected static CuratorSession $session;

    public static function setUpBeforeClass(): void
    {
        self::$porter = FixtureFactory::createPorter();
        self::$session = FixtureFactory::createCuratorSession(self::$porter);
    }

    protected static function createReview(int $appId): void
    {
        $review = self::$porter->importOne(new Import(
            new PutCuratorReview(
                self::$session,
                self::CURATOR_ID,
                new CuratorReview($appId, 'foo', RecommendationState::INFORMATIONAL)
            )
        ));

        self::assertArrayHasKey('success', $review);
        self::assertSame(1, $review['success']);
    }
}
