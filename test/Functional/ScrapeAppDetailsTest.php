<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeAppDetails;
use ScriptFUSION\Porter\Provider\Steam\Scrape\InvalidMarkupException;
use ScriptFUSION\Porter\Provider\Steam\Scrape\SteamDeckCompatibility;
use ScriptFUSION\Porter\Provider\Steam\Scrape\SteamStoreException;
use ScriptFUSIONTest\Porter\Provider\Steam\Fixture\ScrapeAppFixture;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see ScrapeAppDetails
 */
final class ScrapeAppDetailsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Porter $porter;

    protected function setUp(): void
    {
        $this->porter = FixtureFactory::createPorter();
    }

    /**
     * Tests that all supported fields can be scraped from a game page bisynchronously.
     *
     * @see http://store.steampowered.com/app/10/
     *
     * @group type
     */
    public function testGame(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(10)));

        self::assertSame('Counter-Strike', $app['name']);
        self::assertSame(10, $app['app_id']);
        self::assertSame(10, $app['canonical_id']);
        self::assertSame('game', $app['type']);
        self::assertStringStartsWith('Play the world\'s number 1 online action game.', $app['blurb']);
        self::assertSame('2000-11-01T00:00:00+00:00', $app['release_date']->format('c'));
        self::assertCount(1, $app['developers']);
        self::assertSame('valve', current($app['developers']));
        self::assertSame('Valve', key($app['developers']));
        self::assertCount(1, $app['publishers']);
        self::assertSame('valve', current($app['publishers']));
        self::assertSame('Valve', key($app['publishers']));
        self::assertContains('Action', $app['genres']);
        self::assertNotEmpty($app['capsule_url']);

        self::assertCount(8, $languages = $app['languages']);
        self::assertContains('English', $languages);
        self::assertContains('French', $languages);
        self::assertContains('German', $languages);
        self::assertContains('Italian', $languages);
        self::assertContains('Spanish - Spain', $languages);
        self::assertContains('Simplified Chinese', $languages);
        self::assertContains('Traditional Chinese', $languages);
        self::assertContains('Korean', $languages);

        self::assertSame($app['price'], 999);
        self::assertFalse($app['vrx']);
        self::assertFalse($app['free']);
        self::assertFalse($app['adult']);

        self::assertCount(0, $app['videos']);

        self::assertIsInt($app['positive_reviews']);
        self::assertIsInt($app['negative_reviews']);
        self::assertGreaterThan(100000, $total = $app['positive_reviews'] + $app['negative_reviews']);
        self::assertGreaterThan(50000, $app['steam_reviews']);
        self::assertLessThan($total, $app['steam_reviews']);

        self::assertTrue($app['windows']);
        self::assertTrue($app['linux']);
        self::assertTrue($app['mac']);
        // https://twitter.com/SteamVR/status/1600663221731749888
        self::assertArrayNotHasKey('vive', $app, 'VR platforms removed by Valve on December 7th, 2022.');
        self::assertArrayNotHasKey('oculus', $app);
        self::assertArrayNotHasKey('wmr', $app);
        self::assertArrayNotHasKey('valve_index', $app);

        self::assertNull($app['demo_id']);
        self::assertNull($app['bundle_id'], 'App is not sold exclusively as a bundle.');

        foreach ($app['tags'] as $tag) {
            self::assertArrayHasKey('name', $tag);
            self::assertIsString($tagName = $tag['name']);

            // Tags should not contain any whitespace
            self::assertStringNotContainsString("\r", $tagName);
            self::assertStringNotContainsString("\n", $tagName);
            self::assertStringNotContainsString("\t", $tagName);

            // Tags should not start or end with spaces.
            self::assertStringStartsNotWith(' ', $tagName);
            self::assertStringEndsNotWith(' ', $tagName);

            // Tags should not include the "add" tag.
            self::assertNotSame('+', $tagName);
        }
    }

    /**
     * Tests that apps redirecting to another page throw an exception.
     *
     * @see http://store.steampowered.com/app/5/
     */
    public function testHiddenApp(): void
    {
        $this->expectException(InvalidAppIdException::class);
        $this->expectExceptionMessage((string)$appId = 5);

        $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));
    }

    /**
     * Tests that age-restricted content can be scraped.
     *
     * @see http://store.steampowered.com/app/232770/
     */
    public function testAgeRestrictedContent(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(232770)));

        self::assertSame('POSTAL', $app['name']);
    }

    /**
     * Tests that mature content can be scraped.
     *
     * @see http://store.steampowered.com/app/292030/
     */
    public function testMatureContent(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(292030)));

        self::assertSame('The Witcher 3: Wild Hunt', $app['name']);
    }

    /**
     * Tests that an app that is a child of another app points to the parent app ID but retains its own canonical ID.
     *
     * @see https://store.steampowered.com/app/8780/RACE_On/
     */
    public function testChildApp(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId = 8780)));

        self::assertSame(8600, $app['app_id']);
        self::assertSame($appId, $app['canonical_id']);
    }

    /**
     * Tests that an app that is an alias of another app has a different appId and canonicalId to itself.
     *
     * @see https://store.steampowered.com/app/900883/The_Elder_Scrolls_IV_Oblivion_Game_of_the_Year_Edition_Deluxe/
     */
    public function testAliasedApp(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(900883)));

        self::assertSame($parentId = 22330, $app['app_id']);
        self::assertSame($parentId, $app['canonical_id']);
    }

    /**
     * @see http://store.steampowered.com/app/1840/
     *
     * @group type
     */
    public function testSoftware(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(1840)));

        self::assertSame('Source Filmmaker', $app['name']);
        self::assertSame('software', $app['type']);
        /*
         * In some territories this date is shown as the 11th. Our client always has the default territory (presumably
         * US) because it doesn't save Valve's cookies.
         */
        self::assertSame('2012-07-10T00:00:00+00:00', $app['release_date']->format('c'));
    }

    /**
     * @see https://store.steampowered.com/app/378648/The_Witcher_3_Wild_Hunt__Blood_and_Wine
     *
     * @group type
     */
    public function testDlc(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(378648)));

        self::assertSame('The Witcher 3: Wild Hunt - Blood and Wine', $app['name']);
        self::assertSame('dlc', $app['type']);
        self::assertSame('2016-05-30', $app['release_date']->format('Y-m-d'));
    }

    /**
     * Tests that DLC that is no longer available for purchase is parsed correctly.
     *
     * @see https://store.steampowered.com/app/1258510/DEATH_STRANDING_Digital_Art_Book/
     *
     * @group type
     */
    public function testRetiredDlc()
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(1258510)));

        self::assertSame('dlc', $app['type']);
    }

    /**
     * @see https://store.steampowered.com/app/31500/COIL/
     *
     * @group type
     */
    public function testDemo(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(31500)));

        self::assertSame('COIL', $app['name']);
        self::assertSame('demo', $app['type']);
    }

    /**
     * @see https://store.steampowered.com/app/1255980/Portal_Reloaded/
     *
     * @group type
     */
    public function testMod(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(1255980)));

        self::assertSame('Portal Reloaded', $app['name']);
        self::assertSame('mod', $app['type']);
    }

    /**
     * @see https://store.steampowered.com/app/598190/Hollow_Knight__Official_Soundtrack/
     *
     * @group type
     */
    public function testSoundtrack(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(598190)));

        self::assertSame('Hollow Knight - Official Soundtrack', $app['name']);
        self::assertSame('soundtrack', $app['type']);
    }

    /**
     * @see https://store.steampowered.com/app/697440/POSTAL_The_Movie/
     *
     * @group type
     */
    public function testVideo(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(697440)));

        self::assertSame('POSTAL The Movie', $app['name']);
        self::assertSame('video', $app['type']);
    }

    /**
     * @dataProvider provideSeries
     *
     * @group type
     */
    public function testSeries(int $appId, string $appName): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertSame($appName, $app['name']);
        self::assertSame('series', $app['type']);
    }

    /**
     * @see https://store.steampowered.com/app/413850
     * @see https://store.steampowered.com/app/735940
     */
    public function provideSeries(): iterable
    {
        yield 'Single season' => [735940, 'Hina Logic - from Luck & Logic'];
        yield 'Multiple seasons' => [413850, 'CS:GO Player Profiles'];
    }

    /**
     * Tests that an app with only Windows support is identified correctly.
     *
     * @see http://store.steampowered.com/app/630/
     */
    public function testWindowsOnly(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(630)));

        self::assertTrue($app['windows']);
        self::assertFalse($app['linux']);
        self::assertFalse($app['mac']);
    }

    /**
     * Tests that an app with only Mac support is identified correctly.
     *
     * @see http://store.steampowered.com/app/694180/
     */
    public function testMacOnly(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(694180)));

        self::assertFalse($app['windows']);
        self::assertFalse($app['linux']);
        self::assertTrue($app['mac']);
    }

    /**
     * Tests that a game that is region locked throws a parser exception.
     * Dishonored RHCP is region locked to Russia, Hungary, Czech Republic and Poland.
     *
     * @see http://store.steampowered.com/app/217980/
     */
    public function testRegionLockedApp(): void
    {
        $this->expectException(SteamStoreException::class);
        $this->expectExceptionMessage('This item is currently unavailable in your region');

        $this->porter->importOne(new Import(new ScrapeAppDetails(217980)));
    }

    /**
     * Tests that a game with no reviews parses correctly.
     *
     * @see http://store.steampowered.com/app/1620/
     */
    public function testNoReviews(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(1620)));

        self::assertSame(0, $app['positive_reviews']);
        self::assertSame(0, $app['negative_reviews']);
    }

    /**
     * Tests that a game with an invalid date, like "coming soon", is treated as null.
     *
     * @see http://store.steampowered.com/app/271260/
     */
    public function testInvalidDate(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppFixture('invalid date.html')));

        self::assertArrayHasKey('release_date', $app);
        self::assertNull($app['release_date']);
    }

    /**
     * Tests that a game with an invalid date, like "2021", is treated as null.
     *
     * @see https://store.steampowered.com/app/1468600/Matias_Candia_LORD_DOOMER/
     */
    public function testInvalidDateYearOnly(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppFixture('invalid date (year only).html')));

        self::assertArrayHasKey('release_date', $app);
        self::assertNull($app['release_date']);
    }

    /**
     * Tests that a game with multiple developers is parsed correctly.
     *
     * @see https://store.steampowered.com/app/606680/Silver/
     */
    public function testDevelopers(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(606680)));

        self::assertArrayHasKey('developers', $app);
        self::assertCount(3, $developers = $app['developers']);
        self::assertNull(current($developers));
        self::assertSame('Infogrames', key($developers));
        self::assertNull(next($developers));
        self::assertSame('Spiral House', key($developers));
        self::assertSame('THQNordic', next($developers));
        self::assertSame('THQ Nordic', key($developers));
    }

    /**
     * Tests that a game with no developer is parsed correctly.
     *
     * @see https://store.steampowered.com/app/211202/Golden_Axe_III/
     */
    public function testNoDeveloper(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(211202)));

        self::assertArrayHasKey('developers', $app);
        self::assertCount(0, $app['developers']);

        self::assertArrayHasKey('publishers', $app);
        self::assertCount(1, $publishers = $app['publishers']);
        self::assertSame('Sega', current($publishers));
        self::assertSame('SEGA', key($publishers));
    }

    /**
     * Tests that a game with multiple publishers is parsed correctly.
     *
     * @see https://store.steampowered.com/app/748490/The_Legend_of_Heroes_Trails_of_Cold_Steel_II/
     */
    public function testPublishers(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(748490)));

        self::assertArrayHasKey('publishers', $app);
        self::assertCount(2, $publishers = $app['publishers']);
        self::assertSame('xseedgames', current($publishers));
        self::assertSame('XSEED Games', key($publishers));
        self::assertSame('xseedgames', next($publishers));
        self::assertSame('Marvelous USA, Inc.', key($publishers));
    }

    /**
     * Tests that a game with no publisher is parsed correctly.
     *
     * @see https://store.steampowered.com/app/253630/Steam_Marines/
     */
    public function testNoPublisher(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(253630)));

        self::assertArrayHasKey('developers', $app);
        self::assertCount(1, $developers = $app['developers']);
        self::assertNull(current($developers));
        self::assertSame('Worthless Bums', key($developers));

        self::assertArrayHasKey('publishers', $app);
        self::assertCount(0, $app['publishers']);
    }

    /**
     * Tests that a game with a demo correctly parses the full game's platforms instead of the demo's platforms.
     *
     * @see https://github.com/250/Steam-250/issues/33
     * @see https://store.steampowered.com/app/206190/
     */
    public function testPlatformsWithDemo(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(206190)));

        self::assertTrue($app['windows']);
        self::assertTrue($app['mac']);
        self::assertTrue($app['linux']);
    }

    /**
     * Tests that a game with multiple tags has tag names and vote counts parsed correctly.
     */
    public function testTags(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppFixture('tags.html')));

        self::assertArrayHasKey('tags', $app);
        self::assertCount(5, $tags = $app['tags']);

        foreach ($tags as $tag) {
            self::assertArrayHasKey('tagid', $tag);
            self::assertArrayHasKey('name', $tag);
            self::assertArrayHasKey('count', $tag);
        }

        self::assertSame(493, $tags[0]['tagid']);
        self::assertSame('Early Access', $tags[0]['name']);
        self::assertSame(24, $tags[0]['count']);
        self::assertArrayNotHasKey('browseable', $tags[0]);

        self::assertArrayHasKey('browseable', $tags[1]);
        self::assertTrue($tags[1]['browseable']);
    }

    /**
     * Tests that a game with multiple genres with spaces and symbols in their names are parsed correctly.
     *
     * @see http://store.steampowered.com/app/1840/
     */
    public function testGenres(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(1840)));

        self::assertArrayHasKey('genres', $app);
        self::assertCount(2, $genres = $app['genres']);
        self::assertContains('Animation & Modeling', $genres);
        self::assertContains('Video Production', $genres);
    }

    /**
     * Tests that a game with no English language support, and at least two other languages, is parsed correctly.
     *
     * @see http://store.steampowered.com/app/473460/
     */
    public function testLanguages(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(473460)));

        self::assertGreaterThanOrEqual(2, \count($languages = $app['languages']));
        self::assertContains('Simplified Chinese', $languages);
        self::assertContains('Traditional Chinese', $languages);
        self::assertNotContains('English', $languages);
    }

    /**
     * Tests that a game with a discount is parsed correctly.
     */
    public function testDiscountedGame(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppFixture('discounted.html')));

        self::assertArrayHasKey('price', $app);
        self::assertSame(999, $app['price']);

        self::assertArrayHasKey('discount_price', $app);
        self::assertSame(249, $app['discount_price']);

        self::assertArrayHasKey('discount', $app);
        self::assertSame(75, $app['discount']);
    }

    /**
     * Tests that a game with no discount has a null discount price and a discount percentage of zero.
     *
     * @see http://store.steampowered.com/app/698780/
     */
    public function testZeroDiscount(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(698780)));

        self::assertArrayHasKey('discount_price', $app);
        self::assertNull($app['discount_price']);

        self::assertArrayHasKey('discount', $app);
        self::assertSame(0, $app['discount']);
    }

    /**
     * Tests that a game perpetually in early access is parsed correctly.
     *
     * @see https://store.steampowered.com/app/15540/1_2_3_KICK_IT_Drop_That_Beat_Like_an_Ugly_Baby/
     */
    public function testEarlyAccess(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(15540)));

        self::assertArrayHasKey('genres', $app);
        self::assertNotEmpty($genres = $app['genres']);

        self::assertContains('Early Access', $genres);
    }

    /**
     * Tests that games marked as VR exclusive are correctly detected.
     *
     * @dataProvider provideVrExclusiveApps
     */
    public function testVrExclusive(int $appId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertArrayHasKey('vrx', $app);
        self::assertTrue($app['vrx']);
    }

    /**
     * @see http://store.steampowered.com/app/450390/
     * @see http://store.steampowered.com/app/518580/
     * @see http://store.steampowered.com/app/348250/
     */
    public function provideVrExclusiveApps(): array
    {
        return [
            'Requires a virtual reality headset.' => [450390],
            'Requires the HTC Vive virtual reality headset.' => [518580],
            'Requires one of the following virtual reality headsets' => [348250],
        ];
    }

    /**
     * Tests that discontinued games with and without pricing areas are parsed as having no price instead of 0 price.
     *
     * @dataProvider provideDiscontinuedGames
     */
    public function testDiscontinuedGames(int $appId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertArrayHasKey('price', $app);
        self::assertNull($app['price']);
    }

    /**
     * @see https://store.steampowered.com/app/2700/RollerCoaster_Tycoon_3_Platinum/
     * @see https://store.steampowered.com/app/261570/Ori_and_the_Blind_Forest/
     */
    public function provideDiscontinuedGames(): array
    {
        return [
            'No purchase area' => [2700],
            'Purchase area' => [261570],
        ];
    }

    /**
     * Tests that games marked as 'Free', 'Free to Play' or having no price are detected as being cost-free,
     * where cost-free is defined as having a price of 0 and no discount.
     *
     * @dataProvider provideFreeApps
     */
    public function testFreeGames(int $appId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertArrayHasKey('free', $app);
        self::assertTrue($app['free']);

        self::assertArrayHasKey('price', $app);
        self::assertSame(0, $app['price']);

        self::assertArrayHasKey('discount_price', $app);
        self::assertNull($app['discount_price']);

        self::assertArrayHasKey('discount', $app);
        self::assertSame(0, $app['discount']);
    }

    /**
     * @see http://store.steampowered.com/app/630/
     * @see http://store.steampowered.com/app/570/
     * @see http://store.steampowered.com/app/1840/
     * @see http://store.steampowered.com/app/323130/
     * @see http://store.steampowered.com/app/250600/
     * @see https://store.steampowered.com/app/206480/Dungeons__Dragons_Online
     */
    public function provideFreeApps(): array
    {
        return [
            'Free' => [630],
            'Free to Play' => [570],
            '"Free" button (no price)' => [1840],
            '"Download" button (no price)' => [323130],
            '"Play Game" button (no price)' => [250600],
            'Play for Free!' => [206480],
        ];
    }

    /**
     * Tests that when a game is sold in "editions", the price is parsed correctly.
     *
     * @dataProvider provideEditions
     */
    public function testEditions(int $appId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertNotNull($app['price']);
    }

    /**
     * @see https://store.steampowered.com/app/2495100/Hello_Kitty_Island_Adventure
     * @see https://store.steampowered.com/app/690830/Foundation
     */
    public function provideEditions(): iterable
    {
        return [
            'Single edition' => [2495100],
            'Multiple editions' => [690830],
        ];
    }

    /**
     * Tests that a game with multiple videos has its video IDs parsed correctly.
     *
     * @see https://store.steampowered.com/app/32400/
     */
    public function testVideoIds(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(32400)));

        self::assertArrayHasKey('videos', $app);
        self::assertCount(2, $videos = $app['videos']);

        self::assertStringContainsString('/256662547/', $videos[0]);
        self::assertStringContainsString('/256662555/', $videos[1]);
    }

    /**
     * Tests that a game with a demo area as the first "purchase" area is parsed correctly.
     *
     * @see https://store.steampowered.com/app/766280/
     */
    public function testGameDemo(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(766280)));

        self::assertArrayHasKey('price', $app);
        self::assertGreaterThan(0, $app['price']);
    }

    /**
     * Tests that a game that only appears in a package/"sub" purchase area is parsed correctly.
     *
     * @see https://store.steampowered.com/app/2200/Quake_III_Arena/
     */
    public function testPackage(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(2200)));

        self::assertArrayHasKey('price', $app);
        self::assertGreaterThan(0, $app['price']);
    }

    /**
     * Tests that DLC with no Steam Deck information presents Steam Deck compatibility as "null".
     * Note that games will never have null compatibility anymore.
     *
     * @see https://store.steampowered.com/app/836840/Simon_the_Sorcerer__Legacy_Edition_English/
     */
    public function testSteamDeckAbsent(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(836840)));

        self::assertNull($app['steam_deck']);
    }

    /**
     * Tests that a game with Steam Deck "unknown" compatibility is parsed correctly.
     */
    public function testSteamDeckUnknown(): void
    {
        $app = $this->porter->importOne(new Import(
            new ScrapeAppFixture('steam deck unknown compatibility.html')
        ));

        self::assertSame(SteamDeckCompatibility::UNKNOWN, $app['steam_deck']);
    }

    /**
     * Tests that a game with Steam Deck "unsupported" compatibility is parsed correctly.
     *
     * @see https://store.steampowered.com/app/546560/HalfLife_Alyx/
     */
    public function testSteamDeckUnsupported(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(546560)));

        self::assertSame(SteamDeckCompatibility::UNSUPPORTED, $app['steam_deck']);
    }

    /**
     * Tests that a game with Steam Deck "verified" compatibility is parsed correctly.
     *
     * @see https://store.steampowered.com/app/620/Portal_2/
     */
    public function testSteamDeckVerified(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(620)));

        self::assertSame(SteamDeckCompatibility::VERIFIED, $app['steam_deck']);
    }

    /**
     * Tests that a game with Steam Deck "playable" compatibility is parsed correctly.
     *
     * @see https://store.steampowered.com/app/227380/Dragons_Lair/
     */
    public function testSteamDeckPlayable(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(227380)));

        self::assertSame(SteamDeckCompatibility::PLAYABLE, $app['steam_deck']);
    }

    /**
     * Tests that an EA Play subscription game with an additional regular purchase area is parsed correctly.
     *
     * @see https://store.steampowered.com/app/1237970/Titanfall_2
     * @see https://store.steampowered.com/app/1426210/It_Takes_Two
     * @see https://store.steampowered.com/app/1213210/Command__Conquer_Remastered_Collection
     * @see https://store.steampowered.com/app/1222680/Need_for_Speed_Heat
     * @see https://store.steampowered.com/app/1328660/Need_for_Speed_Hot_Pursuit_Remastered
     * @see https://store.steampowered.com/app/1262540/Need_for_Speed
     * @see https://store.steampowered.com/app/1262580/Need_for_Speed_Payback
     * @see https://store.steampowered.com/app/1262560/Need_for_Speed_Most_Wanted
     * @see https://store.steampowered.com/app/1262600/Need_for_Speed_Rivals
     * @see https://store.steampowered.com/app/1237950/STAR_WARS_Battlefront_II
     * @see https://store.steampowered.com/app/1237980/STAR_WARS_Battlefront
     * @see https://store.steampowered.com/app/1238810/Battlefield_V
     * @see https://store.steampowered.com/app/1238840/Battlefield_1
     * @see https://store.steampowered.com/app/1238860/Battlefield_4
     * @see https://store.steampowered.com/app/1238820/Battlefield_3
     * @see https://store.steampowered.com/app/1238880/Battlefield_Hardline
     * @see https://store.steampowered.com/app/1222690/Dragon_Age_Inquisition
     * @see https://store.steampowered.com/app/1238040/Dragon_Age_II
     * @see https://store.steampowered.com/app/1222700/A_Way_Out
     * @see https://store.steampowered.com/app/17460/Mass_Effect_2007
     * @see https://store.steampowered.com/app/1238020/Mass_Effect_3_N7_Digital_Deluxe_Edition_2012
     * @see https://store.steampowered.com/app/1238000/Mass_Effect_Andromeda_Deluxe_Edition
     * @see https://store.steampowered.com/app/47780/Dead_Space_2
     * @see https://store.steampowered.com/app/1238060/Dead_Space_3
     * @see https://store.steampowered.com/app/7110/Jade_Empire_Special_Edition
     * @see https://store.steampowered.com/app/12830/Operation_Flashpoint_Dragon_Rising
     * @see https://store.steampowered.com/app/3590/Plants_vs_Zombies_GOTY_Edition
     * @see https://store.steampowered.com/app/1262240/Plants_vs_Zombies_Battle_for_Neighborville
     * @see https://store.steampowered.com/app/1238080/Burnout_Paradise_Remastered
     * @see https://store.steampowered.com/app/1233570/Mirrors_Edge_Catalyst
     * @see https://store.steampowered.com/app/1225580/Fe
     * @see https://store.steampowered.com/app/1225590/Sea_of_Solitude
     * @see https://store.steampowered.com/app/24780/SimCity_4_Deluxe_Edition
     * @see https://store.steampowered.com/app/11590/Hospital_Tycoon
     * @see https://store.steampowered.com/app/17390/SPORE
     * @see https://store.steampowered.com/app/11450/Overlord
     * @see https://store.steampowered.com/app/12810/Overlord_II
     * @see https://store.steampowered.com/app/12710/Overlord_Raising_Hell
     * @see https://store.steampowered.com/app/12770/Rise_of_the_Argonauts
     * @see https://store.steampowered.com/app/1225560/Unravel
     * @see https://store.steampowered.com/app/1225570/Unravel_Two
     * @see https://store.steampowered.com/app/3480/Peggle_Deluxe
     * @see https://store.steampowered.com/app/3540/Peggle_Nights
     *
     * @dataProvider provideEaPlayRegularPurchaseAreas
     */
    public function testEaPlayRegularPurchase(int $appId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertArrayHasKey('price', $app);
        self::assertGreaterThan(0, $app['price']);
    }

    public static function provideEaPlayRegularPurchaseAreas(): iterable
    {
        return [
            'Titanfall 2' => [1237970],
            'It Takes Two' => [1426210],
            'Command  Conquer Remastered Collection' => [1213210],
            'Need for Speed Heat' => [1222680],
            'Need for Speed Hot Pursuit Remastered' => [1328660],
            'Need for Speed' => [1262540],
            'Need for Speed Payback' => [1262580],
            'Need for Speed Most Wanted' => [1262560],
            'Need for Speed Rivals' => [1262600],
            'STAR WARS Battlefront II' => [1237950],
            'STAR WARS Battlefront' => [1237980],
            'Battlefield V' => [1238810],
            'Battlefield 1' => [1238840],
            'Battlefield 4' => [1238860],
            'Battlefield 3' => [1238820],
            'Battlefield Hardline' => [1238880],
            'Dragon Age Inquisition' => [1222690],
            'Dragon Age II' => [1238040],
            'A Way Out' => [1222700],
            'Mass Effect 2007' => [17460],
            'Mass Effect 3 N7 Digital Deluxe Edition 2012' => [1238020],
            'Mass Effect Andromeda Deluxe Edition' => [1238000],
            'Dead Space 2' => [47780],
            'Dead Space 3' => [1238060],
            'Jade Empire Special Edition' => [7110],
            'Operation Flashpoint Dragon Rising' => [12830],
            'Plants vs Zombies GOTY Edition' => [3590],
            'Plants vs Zombies Battle for Neighborville' => [1262240],
            'Medal of Honor' => [47790],
            'Crysis 2  Maximum Edition' => [108800],
            'Burnout Paradise Remastered' => [1238080],
            'Mirrors Edge' => [17410],
            'Mirrors Edge Catalyst' => [1233570],
            'Fe' => [1225580],
            'Sea of Solitude' => [1225590],
            'SimCity 4 Deluxe Edition' => [24780],
            'Hospital Tycoon' => [11590],
            'SPORE' => [17390],
            'Overlord' => [11450],
            'Overlord II' => [12810],
            'Overlord Raising Hell' => [12710],
            'Rise of the Argonauts' => [12770],
            'Unravel' => [1225560],
            'Unravel Two' => [1225570],
            'Peggle Deluxe' => [3480],
            'Peggle Nights' => [3540],
        ];
    }

    /**
     * Tests that an EA Play subscription game that can also be purchased as part of a bundle, but cannot be purchased
     * separately, has its price parsed and calculated correctly.
     *
     * @see https://store.steampowered.com/app/2229850/Command__Conquer_Red_Alert_2_and_Yuris_Revenge/
     */
    public function testEaPlayBundlePurchase(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(2229850)));

        self::assertSame(39394, $app['bundle_id'], 'Game is only available in a bundle.');

        self::assertSame(1988, $price = $app['price']);
        self::assertArrayHasKey('discount', $app);
        self::assertIsInt($app['discount']);

        if ($app['discount'] === 0) {
            self::assertSame($price, $app['discount_price'], 'Discount price must match price when not discounted.');
        } else {
            self::assertLessThan($price, $app['discount_price'], 'Discounted price must be less than list price.');
            self::assertGreaterThan(0, $app['discount_price'], 'Discounted price must be greater than zero.');
        }
    }

    /**
     * Tests that an app that is only available via regular subscription is parsed without errors.
     *
     * @see https://store.steampowered.com/app/1202520/Fallout_1st/
     */
    public function testSubscriptionOnly(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails(1202520)));

        self::assertNull($app['DEBUG_primary_sub_id']);
    }

    /**
     * Tests that apps with multiple purchase areas are parsed correctly by picking the correct sub ID.
     *
     * @see https://store.steampowered.com/app/57620/Patrician_IV
     * @see https://store.steampowered.com/app/12150/Max_Payne_2_The_Fall_of_Max_Payne
     * @see https://store.steampowered.com/app/15520/AaAaAA__A_Reckless_Disregard_for_Gravity
     * @see https://store.steampowered.com/app/21000/LEGO_Batman_The_Videogame
     * @see https://store.steampowered.com/app/24440/King_Arthur_Knights_and_Vassals_DLC
     * @see https://store.steampowered.com/app/1449880/Mortal_Kombat_11_Kombat_Pack_2
     * @see https://store.steampowered.com/app/202200/Galactic_Civilizations_II_Ultimate_Edition
     * @see https://store.steampowered.com/app/206480/Dungeons__Dragons_Online
     * @see https://store.steampowered.com/app/221001/FTL_Faster_Than_Light__Soundtrack
     * @see https://store.steampowered.com/app/519860/DUSK

     * @see https://store.steampowered.com/app/214560/Mark_of_the_Ninja
     * @see https://store.steampowered.com/app/782330/DOOM_Eternal
     * @see https://store.steampowered.com/app/335300/DARK_SOULS_II_Scholar_of_the_First_Sin
     * @see https://store.steampowered.com/app/24400/King_Arthur__The_Roleplaying_Wargame
     * @see https://store.steampowered.com/app/26800/Braid
     * @see https://store.steampowered.com/app/238240/Edge_of_Space
     * @see https://store.steampowered.com/app/256390/MotoGP14
     * @see https://store.steampowered.com/app/274170/Hotline_Miami_2_Wrong_Number
     * @see https://store.steampowered.com/app/345660/RIDE
     * @see https://store.steampowered.com/app/355130/MotoGP15
     *
     * @dataProvider provideMultiPurchaseAreas
     */
    public function testMultiPurchaseArea(int $appId, int $subId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertTrue($app['windows'] || $app['type'] === 'soundtrack');
        self::assertSame($subId, $app['DEBUG_primary_sub_id']);
    }

    public function provideMultiPurchaseAreas(): iterable
    {
        return [
            // Purchase area appears first but title does not match.
            'Patrician IV' => [57620, 6089],
            'Max Payne 2: The Fall of Max Payne' => [12150, 597],
            'AaAaAA!!! - A Reckless Disregard for Gravity' => [15520, 2062],
            'LEGO® Batman™: The Videogame' => [21000, 1016],
            'King Arthur: Knights and Vassals DLC' => [24440, 2796],
            'Mortal Kombat 11 Kombat Pack 2' => [1449880, 510130],
            'Galactic Civilizations® II: Ultimate Edition' => [202200, 12481],
            'FTL: Faster Than Light - Soundtrack' => [221001, 16706],
            'DUSK' => [519860, 329111],

            // Purchase area does not appear first.
            'Mark of the Ninja' => [214560, 271120],
            'DOOM Eternal' => [782330, 235874],
            'DARK SOULS™ II: Scholar of the First Sin' => [335300, 55366],
            'King Arthur - The Role-playing Wargame' => [24400, 2538],
            'Edge of Space' => [238240, 28923],
            'MotoGP™14' => [256390, 52513],
            'Hotline Miami 2: Wrong Number' => [274170, 37088],
            'RIDE' => [345660, 60272],
            'MotoGP™15' => [355130, 62485],
        ];
    }

    /**
     * Tests that the parser will not throw an exception parsing this custom app page for Valve hardware.
     *
     * This bizarre app page will mostly parse as nulls. Since we are not interested in parsing this, this is fine.
     *
     * @see https://store.steampowered.com/app/1059530/Valve_Index_Headset/
     *
     * @group type
     */
    public function testValveIndex(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId = 1059530)));

        self::assertSame('hardware', $app['type']);
        self::assertSame('Valve Index® Headset', $app['name']);
        self::assertSame($appId, $app['app_id']);

        // App has no tags, unlike every other app class on Steam.
        self::assertIsArray($app['tags']);
        self::assertEmpty($app['tags']);

        // Since there are no tags, our current canonical lookup method fails.
        self::assertNull($app['canonical_id']);

        // App has no platform information.
        self::assertFalse($app['windows']);

        // Although this information is present on the page, we are currently not parsing it due to its different form.
        self::assertNull($app['price']);
        self::assertNull($app['release_date']);
    }

    /**
     * Tests that an adult game behind a login wall can be accessed and parsed.
     *
     * @see https://store.steampowered.com/app/1296770/Her_New_Memory__Hentai_Simulator/
     *
     * @group type
     */
    public function testAdultGame(): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId = 1296770)));

        self::assertSame('Her New Memory - Hentai Simulator', $app['name']);
        self::assertSame('game', $app['type']);
        self::assertTrue($app['adult']);
        self::assertSame($appId, $app['app_id']);
        self::assertSame($appId, $app['canonical_id']);
        self::assertTrue($app['windows']);
        self::assertNotEmpty($app['tags']);
        self::assertNotEmpty($app['languages']);
    }

    /**
     * Tests that an app with a demo app ID is parsed correctly.
     *
     * @see https://store.steampowered.com/app/221910/The_Stanley_Parable/
     * @see https://store.steampowered.com/app/3590/Plants_vs_Zombies_GOTY_Edition/
     *
     * @dataProvider provideDemoIds
     */
    public function testDemoGame(int $appId, int $demoId): void
    {
        $app = $this->porter->importOne(new Import(new ScrapeAppDetails($appId)));

        self::assertSame($app['demo_id'], $demoId);
    }

    public static function provideDemoIds(): iterable
    {
        return [
            'Demo area and purchase area (game)' => [221910, 247750],
            'Demo button in sidebar only (game)' => [3590, 3592],
        ];
    }

    /**
     * Tests that when non-HTML markup is returned, InvalidMarkupException is thrown.
     */
    public function testInvalidMarkup(): void
    {
        $this->expectException(InvalidMarkupException::class);

        $this->porter->importOne(new Import(new ScrapeAppFixture('scuffed.xml')));
    }
}
