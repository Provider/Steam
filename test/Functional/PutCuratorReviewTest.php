<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see PutCuratorReview
 */
final class PutCuratorReviewTest extends TestCase
{
    public function testPutCuratorReview(): void
    {
        $porter = FixtureFactory::createPorter();

        $session = \Amp\Promise\wait(
            CuratorSession::create($porter, $_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])
        );

        $response = \Amp\Promise\wait($porter->importOneAsync(new AsyncImportSpecification(new PutCuratorReview(
            $session,
            $curatorId = '31457321',
            '60',
            'foo',
            'http://example.com'
        ))));

        self::assertInternalType('array', $response);
        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);
        self::assertArrayHasKey('clanid', $response);
        self::assertSame($curatorId, (string)$response['clanid']);
    }
}
