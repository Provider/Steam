<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeAppDetails;
use ScriptFUSION\Porter\Provider\Steam\Scrape\InvalidMarkupException;
use ScriptFUSION\Porter\Provider\Steam\Scrape\SteamStoreException;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSION\Porter\Specification\ImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\Fixture\ScrapeAppFixture;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;
use function Amp\Promise\wait;

/**
 * @see ScrapeAppDetails
 */
final class ScrapeAppDetailsTest extends TestCase
{
    /**
     * @var Porter
     */
    private $porter;

    protected function setUp(): void
    {
        $this->porter = FixtureFactory::createPorter();
    }

    /**
     * Tests that all supported fields can be scraped from a game page bisynchronously.
     *
     * @see http://store.steampowered.com/app/10/
     *
     * @dataProvider provideGameBisync
     */
    public function testGame(\Closure $import): void
    {
        $app = \Closure::bind($import, $this)();

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

        self::assertCount(0, $app['videos']);

        self::assertIsInt($app['positive_reviews']);
        self::assertIsInt($app['negative_reviews']);
        self::assertGreaterThan(100000, $total = $app['positive_reviews'] + $app['negative_reviews']);
        self::assertGreaterThan(50000, $app['steam_reviews']);
        self::assertLessThan($total, $app['steam_reviews']);

        self::assertTrue($app['windows']);
        self::assertTrue($app['linux']);
        self::assertTrue($app['mac']);
        self::assertFalse($app['vive']);
        self::assertFalse($app['occulus']);
        self::assertFalse($app['wmr']);

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
     * Provides a game synchronously and asynchronously.
     */
    public function provideGameBisync(): \Generator
    {
        return $this->provideAppBisync(10);
    }

    private function provideAppBisync(int $appId): \Generator
    {
        yield 'sync' => [function () use ($appId) {
            return $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));
        }, $appId];

        yield 'async' => [function () use ($appId) {
            return wait(
                $this->porter->importOneAsync(new AsyncImportSpecification(new ScrapeAppDetails($appId)))
            );
        }, $appId];
    }

    /**
     * Tests that apps redirecting to another page throw an exception.
     *
     * @see http://store.steampowered.com/app/5/
     *
     * @dataProvider provideHiddenAppBisync
     */
    public function testHiddenApp(\Closure $import, int $appId): void
    {
        $this->expectException(InvalidAppIdException::class);
        $this->expectExceptionMessage("$appId");

        \Closure::bind($import, $this)();
    }

    public function provideHiddenAppBisync(): \Generator
    {
        return $this->provideAppBisync(5);
    }

    /**
     * Tests that age-restricted content can be scraped.
     *
     * @see http://store.steampowered.com/app/232770/
     *
     * @dataProvider provideAgeRestrictedContentBisync
     */
    public function testAgeRestrictedContent(\Closure $import): void
    {
        $app = \Closure::bind($import, $this)();

        self::assertSame('POSTAL', $app['name']);
    }

    public function provideAgeRestrictedContentBisync(): \Generator
    {
        return $this->provideAppBisync(232770);
    }

    /**
     * Tests that mature content can be scraped.
     *
     * @see http://store.steampowered.com/app/292030/
     *
     * @dataProvider provideMatureContentBisync
     */
    public function testMatureContent(\Closure $import): void
    {
        $app = \Closure::bind($import, $this)();

        self::assertSame('The Witcher® 3: Wild Hunt', $app['name']);
    }

    public function provideMatureContentBisync(): \Generator
    {
        return $this->provideAppBisync(292030);
    }

    /**
     * Tests that an app that is a child of another app points to the parent app ID but retains its own canonical ID.
     *
     * @see https://store.steampowered.com/app/1313/SiN_Gold/
     */
    public function testChildApp(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId = 1313)));

        self::assertSame(1300, $app['app_id']);
        self::assertSame($appId, $app['canonical_id']);
    }

    /**
     * Tests that an app that is an alias of another app has a different appId and canonicalId to itself.
     *
     * @see https://store.steampowered.com/app/900883/The_Elder_Scrolls_IV_Oblivion_Game_of_the_Year_Edition_Deluxe/
     */
    public function testAliasedApp(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(900883)));

        self::assertSame($parentId = 22330, $app['app_id']);
        self::assertSame($parentId, $app['canonical_id']);
    }

    /**
     * Tests that apps with no release date are mapped to null.
     *
     * @see http://store.steampowered.com/app/219740/
     */
    public function testNoReleaseDate(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(219740)));

        self::assertSame('Don\'t Starve', $app['name']);
        self::assertNull($app['release_date']);
    }

    /**
     * @see http://store.steampowered.com/app/1840/
     */
    public function testSoftware(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(1840)));

        self::assertSame('Source Filmmaker', $app['name']);
        self::assertSame('software', $app['type']);
        /*
         * In some territories this date is shown as the 11th. Our client always has the default territory (presumably
         * US) because it doesn't save Valve's cookies.
         */
        self::assertSame('2012-07-10T00:00:00+00:00', $app['release_date']->format('c'));
    }

    /**
     * @see http://store.steampowered.com/app/9070/
     */
    public function testDlc(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(9070)));

        self::assertSame('DOOM 3 Resurrection of Evil', $app['name']);
        self::assertSame('dlc', $app['type']);
        self::assertEquals('2005-04-03', $app['release_date']->format('Y-m-d'));
    }

    /**
     * Tests that an app with only Windows support is identified correctly.
     *
     * @see http://store.steampowered.com/app/630/
     */
    public function testWindowsOnly(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(630)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(694180)));

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

        $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(217980)));
    }

    /**
     * Tests that a game with no reviews parses correctly.
     *
     * @see http://store.steampowered.com/app/1620/
     */
    public function testNoReviews(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(1620)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppFixture('invalid date.html')));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppFixture('invalid date (year only).html')));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(606680)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(211202)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(748490)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(253630)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(206190)));

        self::assertTrue($app['windows']);
        self::assertTrue($app['mac']);
        self::assertTrue($app['linux']);
    }

    /**
     * Tests that a game with all VR platforms is correctly identified.
     *
     * @see http://store.steampowered.com/app/552440/
     */
    public function testVrPlatforms(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(552440)));

        self::assertTrue($app['vive']);
        self::assertTrue($app['occulus']);
        self::assertTrue($app['wmr']);
        self::assertTrue($app['valve_index']);
    }

    /**
     * Tests that a game with multiple tags has tag names and vote counts parsed correctly.
     */
    public function testTags(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppFixture('tags.html')));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(1840)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(473460)));

        self::assertGreaterThanOrEqual(2, \count($languages = $app['languages']));
        self::assertContains('Simplified Chinese', $languages);
        self::assertContains('Traditional Chinese', $languages);
    }

    /**
     * Tests that a game with a discount is parsed correctly.
     */
    public function testDiscountedGame(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppFixture('discounted.html')));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(698780)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(15540)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));

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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));

        self::assertArrayHasKey('price', $app);
        self::assertNull($app['price']);
    }

    /**
     * @see http://store.steampowered.com/app/340/
     * @see http://store.steampowered.com/app/261570/
     */
    public function provideDiscontinuedGames(): array
    {
        return [
            'No purchase area' => [340],
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
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));

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
     */
    public function provideFreeApps(): array
    {
        return [
            'Free' => [630],
            'Free to Play' => [570],
            '"Free" button (no price)' => [1840],
            '"Download" button (no price)' => [323130],
            '"Play Game" button (no price)' => [250600],
        ];
    }

    /**
     * Tests that a game with multiple videos has its video IDs parsed correctly.
     *
     * @see https://store.steampowered.com/app/32400/
     */
    public function testVideoIds(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(32400)));

        self::assertArrayHasKey('videos', $app);
        self::assertCount(2, $videos = $app['videos']);
        self::assertContains(256662547, $videos);
        self::assertContains(256662555, $videos);
    }

    /**
     * Tests that a game with a demo area as the first "purchase" area is parsed correctly.
     *
     * @see https://store.steampowered.com/app/766280/
     */
    public function testGameDemo(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(766280)));

        self::assertArrayHasKey('price', $app);
        self::assertGreaterThan(0, $app['price']);
    }

    /**
     * Tests that a game whose primary purchase area does not appear first is parsed correctly.
     *
     * @see https://store.steampowered.com/app/519860/DUSK/
     * @see https://store.steampowered.com/app/214560/Mark_of_the_Ninja/
     * @see https://store.steampowered.com/app/620/Portal_2/
     * @see https://store.steampowered.com/app/546560/HalfLife_Alyx/
     * @dataProvider providePrimaryPurchaseAreaNotFirstApps
     */
    public function testPrimaryPurchaseAreaNotFirst(int $appId, int $subId, int $levenshtein): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));

        self::assertTrue($app['windows']);
        self::assertSame($subId, $app['DEBUG_primary_sub_id']);
        self::assertSame($levenshtein, $app['DEBUG_levenshtein']);
    }

    public function providePrimaryPurchaseAreaNotFirstApps(): iterable
    {
        return [
            'DUSK' => [519860, 329111, 0],
            'Mark of the Ninja' => [214560, 271120, 11],
            'Portal 2' => [620, 7877, 0],
            'Half-Life: Alyx' => [546560, 134870, 0],
        ];
    }

    /**
     * Tests that Grimm is parsed correctly. Grimm is a special case because it's not technically free according to
     * Steam internals but presents itself as if it were, but with an "Install Game" button not typically seen on free
     * games. We will treat it as an ordinary paid game.
     *
     * Note this is a BC break because we used to present Grimm as free.
     *
     * @see https://store.steampowered.com/app/252150/Grimm/
     */
    public function testGrimm(): void
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(252150)));

        self::assertTrue($app['windows']);
        // Either of these sub IDs are acceptable because both represent the same price and platforms.
        self::assertContains($app['DEBUG_primary_sub_id'], [33741, 33744]);
        self::assertSame(11, $app['DEBUG_levenshtein']);
    }

    /**
     * Tests that when non-HTML markup is returned, InvalidMarkupException is thrown.
     */
    public function testInvalidMarkup(): void
    {
        $this->expectException(InvalidMarkupException::class);

        $this->porter->importOne(new ImportSpecification(new ScrapeAppFixture('scuffed.xml')));
    }
}
