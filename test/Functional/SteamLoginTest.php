<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Amp\Http\Cookie\ResponseCookie;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLogin;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;
use function Amp\Promise\wait;

final class SteamLoginTest extends TestCase
{
    /**
     * Tests that the secure login cookie can be obtained from the SteamLogin metadata.
     */
    public function testSecureLoginCookie(): void
    {
        if (!isset($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])) {
            self::markTestSkipped();
        }

        $porter = FixtureFactory::createPorter();

        /** @var AsyncLoginRecord $steamLogin */
        $steamLogin = $porter->importAsync(new AsyncImportSpecification(
            new SteamLogin($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])
        ))->findFirstCollection();

        /** @var SecureLoginCookie $secureLoginCookie */
        $secureLoginCookie = wait($steamLogin->getSecureLoginCookie());

        self::assertInstanceOf(SecureLoginCookie::class, $secureLoginCookie);
        self::assertInstanceOf(ResponseCookie::class, $cookie = $secureLoginCookie->getCookie());
        self::assertNotEmpty($cookie->getValue());
    }
}
