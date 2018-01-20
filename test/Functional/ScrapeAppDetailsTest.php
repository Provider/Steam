<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeAppDetails;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSION\Porter\Specification\ImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\Fixture\ScrapeAppFixture;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see ScrapeAppDetails
 */
final class ScrapeAppDetailsTest extends TestCase
{
    /**
     * @var Porter
     */
    private $porter;

    protected function setUp()
    {
        $this->porter = FixtureFactory::createPorter();
    }

    /**
     * Tests that all supported fields can be scraped from a game page.
     *
     * @see http://store.steampowered.com/app/10/
     */
    public function testGame()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(10)));

        self::assertSame('Counter-Strike', $app['name']);
        self::assertSame('game', $app['type']);
        self::assertSame('2000-11-01T00:00:00+00:00', $app['release_date']->format('c'));
        self::assertContains('Action', $app['genres']);

        self::assertCount(8, $languages = $app['languages']);
        self::assertContains('English', $languages);
        self::assertContains('French', $languages);
        self::assertContains('German', $languages);
        self::assertContains('Italian', $languages);
        self::assertContains('Spanish', $languages);
        self::assertContains('Simplified Chinese', $languages);
        self::assertContains('Traditional Chinese', $languages);
        self::assertContains('Korean', $languages);

        self::assertSame($app['price'], 999);

        self::assertInternalType('int', $app['positive_reviews']);
        self::assertInternalType('int', $app['negative_reviews']);
        self::assertGreaterThan(90000, $app['positive_reviews'] + $app['negative_reviews']);

        self::assertTrue($app['windows']);
        self::assertTrue($app['linux']);
        self::assertTrue($app['mac']);
        self::assertFalse($app['vive']);
        self::assertFalse($app['occulus']);
        self::assertFalse($app['wmr']);

        foreach ($app['tags'] as $tag) {
            self::assertArrayHasKey('name', $tag);
            self::assertInternalType('string', $tagName = $tag['name']);

            // Tags should not contain any whitespace
            self::assertNotContains("\r", $tagName);
            self::assertNotContains("\n", $tagName);
            self::assertNotContains("\t", $tagName);

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
    public function testHiddenApp()
    {
        $this->expectException(InvalidAppIdException::class);
        $this->expectExceptionMessage((string)$appId = 5);

        $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));
    }

    /**
     * Tests that age-restricted content can be scraped.
     *
     * @see http://store.steampowered.com/app/232770/
     */
    public function testAgeRestrictedContent()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(232770)));

        self::assertSame('POSTAL', $app['name']);
    }

    /**
     * Tests that mature content can be scraped.
     *
     * @see http://store.steampowered.com/app/292030/
     */
    public function testMatureContent()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(292030)));

        self::assertSame('The Witcher® 3: Wild Hunt', $app['name']);
    }

    /**
     * Tests that apps with no release date are mapped to null.
     *
     * @see http://store.steampowered.com/app/219740/
     */
    public function testNoReleaseDate()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(219740)));

        self::assertSame('Don\'t Starve', $app['name']);
        self::assertNull($app['release_date']);
    }

    /**
     * @see http://store.steampowered.com/app/1840/
     */
    public function testSoftware()
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
     * @see http://store.steampowered.com/app/323130/
     */
    public function testDlc()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(323130)));

        self::assertSame('Half-Life Soundtrack', $app['name']);
        self::assertSame('dlc', $app['type']);
        self::assertSame('2014-09-24T00:00:00+00:00', $app['release_date']->format('c'));
    }

    /**
     * Tests that an app with only Windows support is identified correctly.
     *
     * @see http://store.steampowered.com/app/630/
     */
    public function testWindowsOnly()
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
    public function testMacOnly()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(694180)));

        self::assertFalse($app['windows']);
        self::assertFalse($app['linux']);
        self::assertTrue($app['mac']);
    }

    /**
     * Dishonored RHCP is region locked to Russia, Hungary, Czech Republic and Poland.
     *
     * @see http://store.steampowered.com/app/217980/
     */
    public function testRegionLockedApp()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('app');

        $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(217980)));
    }

    /**
     * Tests that a game with no reviews parses correctly.
     *
     * @see http://store.steampowered.com/app/1620/
     */
    public function testNoReviews()
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
    public function testInvalidDate()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(271260)));

        self::assertArrayHasKey('release_date', $app);
        self::assertNull($app['release_date']);
    }

    /**
     * Tests that a game with all VR platforms is correctly identified.
     *
     * @see http://store.steampowered.com/app/552440/
     */
    public function testVrPlatforms()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(552440)));

        self::assertTrue($app['vive']);
        self::assertTrue($app['occulus']);
        self::assertTrue($app['wmr']);
    }

    /**
     * Tests that a game with multiple tags has tag names and vote counts parsed correctly.
     */
    public function testTags()
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
    public function testGenres()
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
    public function testLanguages()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(473460)));

        self::assertGreaterThanOrEqual(2, \count($languages = $app['languages']));
        self::assertContains('Simplified Chinese', $languages);
        self::assertContains('Traditional Chinese', $languages);
    }

    /**
     * Tests that a game with a discount is parsed correctly.
     */
    public function testDiscountedGame()
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
    public function testZeroDiscount()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(698780)));

        self::assertArrayHasKey('discount_price', $app);
        self::assertNull($app['discount_price']);

        self::assertArrayHasKey('discount', $app);
        self::assertSame(0, $app['discount']);
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
     * Tests that games marked as 'Free', 'Free to Play' or having no price are detected as being cost-free.
     *
     * @dataProvider provideFreeApps
     */
    public function testFreeGames(int $appId)
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));

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
     * @see http://store.steampowered.com/app/252150/
     */
    public function provideFreeApps(): array
    {
        return [
            'Free' => [630],
            'Free to Play' => [570],
            '"Free" button (no price)' => [1840],
            '"Download" button (no price)' => [323130],
            '"Play Game" button (no price)' => [250600],
            '"Install Game" button (no price)' => [252150],
        ];
    }
}
