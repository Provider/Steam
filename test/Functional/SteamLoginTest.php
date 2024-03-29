<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Amp\Http\Cookie\ResponseCookie;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLogin;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

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
        $steamLogin = $porter->import(new Import(
            new SteamLogin($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])
        ))->findFirstCollection();

        /** @var SecureLoginCookie $secureLoginCookie */
        $secureLoginCookie = $steamLogin->getSecureLoginCookie()->await();

        self::assertInstanceOf(SecureLoginCookie::class, $secureLoginCookie);
        self::assertInstanceOf(ResponseCookie::class, $cookie = $secureLoginCookie->getCookie());
        self::assertNotEmpty($cookie->getValue());
    }
}
