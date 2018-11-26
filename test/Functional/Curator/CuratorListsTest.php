<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use Amp\Loop;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\CuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\DeleteCuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\GetCuratorLists;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\PutCuratorList;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;

/**
 * @see GetCuratorLists
 * @see PutCuratorList
 * @see DeleteCuratorList
 */
final class CuratorListsTest extends CuratorTestCase
{
    /**
     * Tests that a curator list can be created with default parameters.
     *
     * The created list is deleted at the end of the test.
     */
    public function testCreateDefaultList(): void
    {
        try {
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
        } finally {
            self::deleteList($listId);
        }
    }

    /**
     * Tests that a curator list can be created with specific parameters and subsequently found in the list of curator
     * lists with those parameters.
     *
     * The created list is deleted at the end of the test.
     */
    public function testGetCuratorLists(): void
    {
        try {
            // Create list.
            $list = new CuratorList;
            $list->setTitle($title = 'foo');
            $list->setDescription($desc = 'bar');

            $response = \Amp\Promise\wait(self::$porter->importOneAsync(new AsyncImportSpecification(
                new PutCuratorList(self::$session, self::CURATOR_ID, $list)
            )));

            self::assertArrayHasKey('listid', $response);
            self::assertNotEmpty($listId = $response['listid']);

            $lists = self::$porter->importAsync(new AsyncImportSpecification(
                new GetCuratorLists(self::$session, self::CURATOR_ID)
            ));

            Loop::run(static function () use ($lists, $listId, $title, $desc): \Generator {
                $found = false;

                while (yield $lists->advance()) {
                    $list = $lists->getCurrent();

                    if ($list['listid'] !== $listId) {
                        continue;
                    }

                    $found = true;

                    self::assertSame($list['title'], $title);
                    self::assertSame($list['blurb'], $desc);
                }

                self::assertTrue($found, 'Find created curator list in list of curators.');
            });
        } finally {
            self::deleteList($listId);
        }
    }

    private static function deleteList(string $listId): void
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
