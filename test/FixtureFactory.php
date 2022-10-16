<?php

namespace ScriptFUSIONTest\Porter\Provider\Steam;

use Mockery\MockInterface;
use Psr\Container\ContainerInterface;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\StaticDataProvider;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSION\StaticClass;

final class FixtureFactory
{
    use StaticClass;

    private static ?CuratorSession $savedSession = null;

    public static function createPorter(): Porter
    {
        return new Porter(self::mockPorterContainer());
    }

    /**
     * @return ContainerInterface|MockInterface
     */
    public static function mockPorterContainer(): ContainerInterface
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

    public static function createSession(Porter $porter): CuratorSession
    {
        if (self::$savedSession) {
            return self::$savedSession;
        }

        if (isset($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])) {
            return CuratorSession::create($porter, $_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD']);
        }

        return self::$savedSession = CuratorSession::createFromCookie(
            SecureLoginCookie::create($_SERVER['STEAM_COOKIE']),
            $porter
        );
    }
}
