<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\CuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\DeleteCuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\PutCuratorList;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;

/**
 * @see PutCuratorList
 * @see DeleteCuratorList
 */
final class PutCuratorListTest extends CuratorTestCase
{
    /**
     * Tests that a curator list can be created with default parameters.
     */
    public function testCreateList(): void
    {
        // Create list.
        $response = \Amp\Promise\wait(self::$porter->importOneAsync(new AsyncImportSpecification(
            new PutCuratorList(self::$session, self::CURATOR_ID, new CuratorList)
        )));

        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);

        self::assertArrayHasKey('clanid', $response);
        self::assertSame(self::CURATOR_ID, $response['clanid']);

        self::assertArrayHasKey('listid', $response);
        self::assertInternalType('string', $listId = $response['listid']);
        self::assertNotEmpty($listId);

        self::deleteList($listId);
    }

    private static function deleteList($listId): void
    {
        $response = \Amp\Promise\wait(self::$porter->importOneAsync(new AsyncImportSpecification(
            new DeleteCuratorList(self::$session, self::CURATOR_ID, $listId)
        )));

        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);

        self::assertArrayHasKey('clanid', $response);
        self::assertSame(self::CURATOR_ID, $response['clanid']);

        self::assertArrayHasKey('listid', $response);
        self::assertFalse($response['listid']);
    }
}
