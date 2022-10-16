<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\CuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\DeleteCuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\DeleteCuratorListApp;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\GetCuratorLists;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\PatchCuratorListAppOrder;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\PutCuratorList;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList\PutCuratorListApp;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\PutCuratorReview;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\RecommendationState;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;

/**
 * @see GetCuratorLists
 * @see PutCuratorList
 * @see PutCuratorListApp
 * @see PatchCuratorListAppOrder
 * @see DeleteCuratorListApp
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
            $response = self::fetchOneSync(new PutCuratorList(self::$session, self::CURATOR_ID, new CuratorList));

            self::assertArrayHasKey('clanid', $response);
            self::assertSame(self::CURATOR_ID, $response['clanid']);

            self::assertArrayHasKey('listid', $response);
            self::assertIsString($listId = $response['listid']);
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
        $list = new CuratorList;
        $list->setTitle($title = 'foo');
        $list->setDescription($desc = 'bar');

        try {
            // Create list.
            $response = self::fetchOneSync(new PutCuratorList(self::$session, self::CURATOR_ID, $list));

            self::assertArrayHasKey('listid', $response);
            self::assertNotEmpty($listId = $response['listid']);

            // Fetch list.
            $list = self::fetchList($listId);
            self::assertSame($list['title'], $title);
            self::assertSame($list['blurb'], $desc);
        } finally {
            self::deleteList($listId);
        }
    }

    /**
     * Tests that a curator list can have a review added to and removed from it.
     *
     * The created list is deleted at the end of the test, however the review is not.
     */
    public function testPutCuratorListApp(): void
    {
        self::createReview($appId = 10);

        try {
            // Create list.
            $response = self::fetchOneSync(new PutCuratorList(self::$session, self::CURATOR_ID, new CuratorList));

            self::assertArrayHasKey('listid', $response);
            self::assertNotEmpty($listId = $response['listid']);

            // Add review to list.
            self::fetchOneSync(new PutCuratorListApp(self::$session, self::CURATOR_ID, $listId, $appId));

            // Delete review from list.
            self::fetchOneSync(new DeleteCuratorListApp(self::$session, self::CURATOR_ID, $listId, $appId));
        } finally {
            self::deleteList($listId);
        }
    }

    /**
     * Tests that a curator list can have its apps reordered after creation and having apps added to it.
     */
    public function testReorderCuratorList(): void
    {
        self::createReview($appId1 = 20);
        self::createReview($appId2 = 30);

        try {
            // Create list.
            $curatorList = new CuratorList;
            $curatorList->setTitle('foo');

            $response = self::fetchOneSync(new PutCuratorList(self::$session, self::CURATOR_ID, $curatorList));
            self::assertArrayHasKey('listid', $response);
            self::assertNotEmpty($listId = $response['listid']);

            // Add reviews to list.
            self::fetchOneSync(new PutCuratorListApp(self::$session, self::CURATOR_ID, $listId, $appId1));
            self::fetchOneSync(new PutCuratorListApp(self::$session, self::CURATOR_ID, $listId, $appId2));

            // Fetch first app from list.
            $list = self::fetchList($listId);
            usort($list['apps'], static function (array $a, array $b) {
                return $a['sort_order'] <=> $b['sort_order'];
            });
            $firstApp = reset($list['apps']);

            self::assertSame(
                $appId1,
                $firstApp['recommended_app']['appid'],
                'App first added listed first.'
            );

            // Update list order.
            self::fetchOneSync(
                new PatchCuratorListAppOrder(self::$session, self::CURATOR_ID, $listId, [$appId2, $appId1])
            );

            // Fetch first app from list.
            $list = self::fetchList($listId);
            usort($list['apps'], static function (array $a, array $b) {
                return $a['sort_order'] <=> $b['sort_order'];
            });
            $firstApp = reset($list['apps']);

            self::assertSame(
                $appId2,
                $firstApp['recommended_app']['appid'],
                'Second app listed first due to reordering.'
            );
        } finally {
            self::deleteList($listId);
        }
    }

    private static function createReview(int $appId): void
    {
        $review = self::$porter->importOneAsync(new AsyncImportSpecification(
            new PutCuratorReview(
                self::$session,
                self::CURATOR_ID,
                new CuratorReview($appId, 'foo', RecommendationState::INFORMATIONAL())
            )
        ));

        self::assertArrayHasKey('success', $review);
        self::assertSame(1, $review['success']);
    }

    private static function fetchOneSync(AsyncResource $resource): array
    {
        $response = self::$porter->importOneAsync(new AsyncImportSpecification($resource));

        self::assertArrayHasKey('success', $response);
        self::assertSame(1, $response['success']);

        return $response;
    }

    private static function fetchList(string $listId): array
    {
        $lists = self::$porter->importAsync(new AsyncImportSpecification(
            new GetCuratorLists(self::$session, self::CURATOR_ID)
        ));

        $list = \iter\search(fn (array $list) => $list['listid'] === $listId, $lists);
        self::assertNotNull($list, 'Find specified curator list in curator list collection.');

        return $list;
    }

    private static function deleteList(string $listId): void
    {
        $response = self::fetchOneSync(new DeleteCuratorList(self::$session, self::CURATOR_ID, $listId));

        self::assertArrayHasKey('clanid', $response);
        self::assertSame(self::CURATOR_ID, $response['clanid']);

        self::assertArrayHasKey('listid', $response);
        self::assertFalse($response['listid']);
    }
}
