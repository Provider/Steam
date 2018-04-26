<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Amp\Artax\Cookie\Cookie;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLogin;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

final class SteamLoginTest extends TestCase
{
    /**
     * Tests that the secure login cookie can be obtained from the SteamLogin metadata.
     */
    public function testSecureLoginCookie(): void
    {
        $porter = FixtureFactory::createPorter();

        /** @var AsyncLoginRecord $steamLogin */
        $steamLogin = $porter->importAsync(new AsyncImportSpecification(
            new SteamLogin($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])
        ))->findFirstCollection();

        $secureLoginCookie = \Amp\Promise\wait($steamLogin->getSecureLoginCookie());

        self::assertInstanceOf(Cookie::class, $secureLoginCookie);
        self::assertNotEmpty($secureLoginCookie->getValue());
    }
}
