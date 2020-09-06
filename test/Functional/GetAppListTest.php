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
     * Tests that when downloading the complete list of Steam apps, the list contains an appropriate number of entries
     * and each entry is well-formed.
     *
     * @dataProvider provideAppListApiKeys
     */
    public function testAppList(?string $key, int $expected): void
    {
        $porter = FixtureFactory::createPorter();

        $apps = $porter->import(new ImportSpecification(new GetAppList($key)));

        $count = 0;
        foreach ($apps as $app) {
            ++$count;
            self::assertArrayHasKey('appid', $app);
            self::assertIsInt($app['appid']);

            self::assertArrayHasKey('name', $app);
            self::assertIsString($app['name']);
        }

        self::assertGreaterThanOrEqual($expected, $count);
    }

    public function provideAppListApiKeys(): iterable
    {
        return [
            'No key' => [null, 100000],
            'Api key' => [$_SERVER['STEAM_API_KEY'], 71000],
        ];
    }
}
