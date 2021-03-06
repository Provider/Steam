<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\User;

use Amp\PHPUnit\AsyncTestCase;
use ScriptFUSION\Porter\Provider\Steam\Resource\User\ScrapeUserProfile;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

/**
 * @see ScrapeUserProfile
 */
final class ScrapeUserProfileTest extends AsyncTestCase
{
    /**
     * Tests that scraping GabeN's user profile returns his user name.
     */
    public function testScrapeGabenProfile(): \Generator
    {
        $porter = FixtureFactory::createPorter();
        $profile = yield $porter->importOneAsync(new AsyncImportSpecification(
            new ScrapeUserProfile(new \SteamID('76561197960287930'))
        ));

        self::assertArrayHasKey('name', $profile);
        self::assertSame('Rabscuttle', $profile['name']);

        self::assertArrayHasKey('image_hash', $profile);
        self::assertSame('c5d56249ee5d28a07db4ac9f7f60af961fab5426', $profile['image_hash']);
    }
}
