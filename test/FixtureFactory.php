<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam;

use Amp\Http\Cookie\ResponseCookie;
use Mockery\MockInterface;
use Psr\Container\ContainerInterface;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\StaticDataProvider;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\CommunitySession;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSION\StaticClass;

final class FixtureFactory
{
    use StaticClass;

    private static ResponseCookie $secureLoginCookie;

    public static function createPorter(ContainerInterface $container = null): Porter
    {
        return new Porter($container ?? self::mockPorterContainer());
    }

    public static function mockPorterContainer(): ContainerInterface|MockInterface
    {
        return \Mockery::mock(ContainerInterface::class)
            ->shouldReceive('has')
                ->with(SteamProvider::class)
                ->andReturn(true)
            ->shouldReceive('has')
                ->with(StaticDataProvider::class)
                ->andReturn(false)
            ->shouldReceive('get')
                ->with(SteamProvider::class)
                ->andReturn(new SteamProvider)
                ->byDefault()
            ->getMock()
        ;
    }

    public static function createCuratorSession(Porter $porter): CuratorSession
    {
        static $session;

        if ($session) {
            return $session;
        }

        if (isset(self::$secureLoginCookie)) {
            return $session =
                CuratorSession::createFromCookie(new SecureLoginCookie(self::$secureLoginCookie), $porter);
        }

        if (isset($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])) {
            return $session = CuratorSession::create($porter, $_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD']);
        }

        $session = CuratorSession::createFromCookie(
            SecureLoginCookie::create($_SERVER['STEAM_COOKIE']),
            $porter
        );

        self::$secureLoginCookie = $session->getSecureLoginCookie();

        return $session;
    }

    public static function createCommunitySession(Porter $porter): CommunitySession
    {
        static $session;

        if ($session) {
            return $session;
        }

        if (isset(self::$secureLoginCookie)) {
            return $session = new CommunitySession(new SecureLoginCookie(self::$secureLoginCookie));
        }

        $session = CommunitySession::create($porter, $_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD']);

        self::$secureLoginCookie = $session->getSecureLoginCookie();

        return $session;
    }
}
