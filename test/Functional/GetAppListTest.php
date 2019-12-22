<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Resource\GetAppList;
use ScriptFUSION\Porter\Specification\ImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see GetAppList
 */
final class GetAppListTest extends TestCase
{
    /**
     * Tests that when downloading the complete list of Steam apps, the list contains at least 49000 entries and each
     * entry is well-formed.
     */
    public function testAppList(): void
    {
        $porter = FixtureFactory::createPorter();

        $apps = $porter->import(new ImportSpecification(new GetAppList));
        self::assertGreaterThan(49000, count($apps));

        foreach ($apps as $app) {
            self::assertArrayHasKey('appid', $app);
            self::assertIsInt($app['appid']);

            self::assertArrayHasKey('name', $app);
            self::assertIsString($app['name']);
            self::assertNotEmpty($app['name']);
        }
    }
}
