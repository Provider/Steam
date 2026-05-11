<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Resource\GetAppAssets;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see GetAppAssets
 */
final class GetAppAssetsTest extends TestCase
{
    public function testSingleApp(): void
    {
        $porter = FixtureFactory::createPorter();

        $apps = iterator_to_array($porter->import(new Import(new GetAppAssets([10]))));

        self::assertIsArray($apps);
        self::assertCount(1, $apps);
        self::assertIsArray($app = $apps[0]);
        self::assertSame(10, $app['id']);
        self::assertIsArray($assets = $app['assets']);
        self::assertArrayHasKey('hero_capsule', $assets);
        self::assertNotEmpty($assets['hero_capsule']);
    }

    public function testMultipleApps(): void
    {
        $porter = FixtureFactory::createPorter();

        $apps = iterator_to_array($porter->import(new Import(new GetAppAssets([10, 20]))));

        self::assertIsArray($apps);
        self::assertCount(2, $apps);
    }
}
