<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeAppDetails;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSION\Porter\Specification\ImportSpecification;
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

        foreach ($app['tags'] as $tag) {
            // Tags should not contain any whitespace
            self::assertNotContains("\r", $tag);
            self::assertNotContains("\n", $tag);
            self::assertNotContains("\t", $tag);

            // Tags should not start or end with spaces.
            self::assertStringStartsNotWith(' ', $tag);
            self::assertStringEndsNotWith(' ', $tag);

            // Tags should not include the "add" tag.
            self::assertNotSame('+', $tag);
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
}
