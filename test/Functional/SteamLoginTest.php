<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional;

use Amp\Http\Cookie\ResponseCookie;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLogin;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLoginException;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;
use ScriptFUSIONTest\Porter\Provider\Steam\MockFactory;

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

    /**
     * Tests that when login fails, it does not leave an unobserved future that crashes the event loop when collected.
     */
    public function testFailedLoginLeavesNoUnhandledFuture(): void
    {
        // Passes by not crashing: any unobserved error surfaces when the loop is pumped below.
        self::expectNotToPerformAssertions();

        $porter = FixtureFactory::createPorter($container = FixtureFactory::mockPorterContainer());
        $container->expects('get')->with(SteamProvider::class)
            ->andReturn(new SteamProvider($connector = \Mockery::mock(HttpConnector::class)));
        MockFactory::mockConnectorResponse($connector, '{"response":{}}');

        try {
            $porter->import(new Import(new SteamLogin('Alfa', 'Bravo')))->findFirstCollection();

            self::fail('Expected SteamLoginException.');
        } catch (SteamLoginException) {
            // Expected: invalid credentials.
        }

        // Destroy the orphaned login future and pump the loop: any unobserved error crashes here.
        gc_collect_cycles();
        \Amp\delay(.001);
    }
}
