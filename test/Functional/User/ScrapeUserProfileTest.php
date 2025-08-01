<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\User;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Connector\Connector;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Resource\User\ScrapeUserProfile;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSIONTest\Porter\Provider\Steam\Fixture\ScrapeAppFixture;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;
use ScriptFUSIONTest\Porter\Provider\Steam\MockFactory;
use xPaw\Steam\SteamID;

/**
 * @see ScrapeUserProfile
 */
final class ScrapeUserProfileTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Tests that scraping GabeN's user profile returns his username.
     */
    public function testScrapeGabenProfile(): void
    {
        $porter = FixtureFactory::createPorter();
        $profile = $porter->importOne(new Import(new ScrapeUserProfile(new SteamID('76561197960287930'))));

        self::assertArrayHasKey('name', $profile);
        self::assertSame('Rabscuttle', $profile['name']);

        self::assertArrayHasKey('image_hash', $profile);
        self::assertSame('c5d56249ee5d28a07db4ac9f7f60af961fab5426', $profile['image_hash']);
        self::assertNull($profile['image_path_fragment']);
    }

    /**
     * Tests that scraping a profile that has a fancy animated border around the avatar fetches the correct image hash.
     */
    public function testScrapeFancyProfile(): void
    {
        $porter = FixtureFactory::createPorter($container = FixtureFactory::mockPorterContainer());
        $container->expects('get')->with(SteamProvider::class)
            ->andReturn(new SteamProvider($connector = \Mockery::mock(Connector::class)));
        MockFactory::mockConnectorResponse(
            $connector,
            ScrapeAppFixture::getFixture('user profile fancy avatar border.html')
        );

        $profile = $porter->importOne(new Import(new ScrapeUserProfile(new SteamID(1))));

        self::assertSame('8cb62010a27329ef8f5bf6d2f8e5dba79d1940ab', $profile['image_hash']);
        self::assertNull($profile['image_path_fragment']);
    }

    /**
     * Tests that scraping a profile that has an animated avatar, provided by an app, is parsed into the image hash.
     */
    public function testScrapeFancyAvatar(): void
    {
        $porter = FixtureFactory::createPorter($container = FixtureFactory::mockPorterContainer());
        $container->expects('get')->with(SteamProvider::class)
            ->andReturn(new SteamProvider($connector = \Mockery::mock(Connector::class)));
        MockFactory::mockConnectorResponse(
            $connector,
            ScrapeAppFixture::getFixture('user profile fancy avatar.html')
        );

        $profile = $porter->importOne(new Import(new ScrapeUserProfile(new SteamID(1))));

        self::assertSame('5a035a299903a7ab20f5662d2f5b7758646c63aa', $profile['image_hash']);
        self::assertSame('1390700/5a035a299903a7ab20f5662d2f5b7758646c63aa.gif', $profile['image_path_fragment']);
    }
}
