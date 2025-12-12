<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\User;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\CommunitySession;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidSessionException;
use ScriptFUSION\Porter\Provider\Steam\Resource\User\ScrapeUserGames;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;
use xPaw\Steam\SteamID;

final class ScrapeUserGamesTest extends TestCase
{
    // https://steamcommunity.com/id/smeehrrr
    private const VALID_STEAM_ID = '76561198040900440';

    /**
     * Tests that a very large public games list can be scraped.
     *
     * @see ScrapeUserGames
     */
    public function testScrapeUserGames(): void
    {
        $porter = FixtureFactory::createPorter();
        $session = FixtureFactory::createCommunitySession($porter);
        $results = $porter->import(new Import(new ScrapeUserGames($session, new SteamID(self::VALID_STEAM_ID))));

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

        self::assertGreaterThan(4_000, $counter, 'More than 4,000 games.');
    }

    /**
     * Tests that when scraping the public games list with an invalid session, an appropriate exception is thrown.
     */
    public function testInvalidSession(): void
    {
        $porter = FixtureFactory::createPorter();
        $session = new CommunitySession(SecureLoginCookie::create('0')); // Invalid.

        $this->expectException(InvalidSessionException::class);

        $results = $porter->import(new Import(new ScrapeUserGames($session, new SteamID(self::VALID_STEAM_ID))));
    }

    /**
     * Tests that scraping a public profile with a private games list throws an appropriate exception.
     *
     * @see https://steamcommunity.com/profiles/76561197989728462
     */
    public function testScrapePublicProfilePrivateGames(): void
    {
        $porter = FixtureFactory::createPorter();
        $session = FixtureFactory::createCommunitySession($porter);

        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserException::EMPTY_GAMES_LIST);

        $results = $porter->import(new Import(new ScrapeUserGames($session, new SteamID('76561197989728462'))));
    }

    /**
     * Tests that scraping a private profile throws an appropriate exception.
     *
     * @see https://steamcommunity.com/id/vAcquiredTaste
     */
    public function testScrapePrivateProfile(): void
    {
        $porter = FixtureFactory::createPorter();
        $session = FixtureFactory::createCommunitySession($porter);

        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserException::NON_PUBLIC);

        $results = $porter->import(new Import(new ScrapeUserGames($session, new SteamID('76561197993329385'))));
    }
}
