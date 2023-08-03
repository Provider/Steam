<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\User;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Resource\User\ScrapeUserGames;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

final class ScrapeUserGamesTest extends TestCase
{
    /**
     * Tests that Al Farnsworth's public games list can be scraped and contains more than 990 games.
     *
     * @see ScrapeUserGames
     * @see https://steamcommunity.com/id/afarnsworth
     */
    public function testScrapeUserGames(): void
    {
        $porter = FixtureFactory::createPorter();
        $session = FixtureFactory::createCommunitySession($porter);
        $results = $porter->import(new Import(new ScrapeUserGames($session, new \SteamID('STEAM_0:0:84447'))));

        $counter = 0;
        foreach ($results as $result) {
            ++$counter;

            self::assertArrayHasKey('appid', $result);
            self::assertArrayHasKey('name', $result);
            self::assertArrayHasKey('playtime_forever', $result);

            self::assertGreaterThan(0, $result['appid']);
            self::assertNotEmpty($result['name']);
            self::assertGreaterThanOrEqual(0, $result['playtime_forever']);
        }

        self::assertGreaterThan(990, $counter, 'More than 990 games.');
    }
}
