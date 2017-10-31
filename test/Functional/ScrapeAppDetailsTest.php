<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\ScrapeAppDetails;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidAppIdException;
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
     * @see http://store.steampowered.com/app/10/
     */
    public function testGame()
    {
        $app = $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails(10)));

        self::assertSame('Counter-Strike', $app['name']);
        self::assertSame('game', $app['type']);
        self::assertSame('2000-11-01T00:00:00+00:00', $app['release_date']->format('c'));
    }

    /**
     * http://store.steampowered.com/app/5/
     */
    public function testHiddenApp()
    {
        $this->expectException(InvalidAppIdException::class);
        $this->expectExceptionMessage((string)$appId = 5);

        $this->porter->importOne(new ImportSpecification(new ScrapeAppDetails($appId)));
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
}
