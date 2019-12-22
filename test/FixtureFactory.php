<?php

namespace ScriptFUSIONTest\Porter\Provider\Steam;

use Amp\Promise;
use Psr\Container\ContainerInterface;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\StaticDataProvider;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSION\StaticClass;
use function Amp\call;

final class FixtureFactory
{
    use StaticClass;

    private static $savedSession;

    public static function createPorter(): Porter
    {
        return new Porter(
            \Mockery::mock(ContainerInterface::class)
                ->shouldReceive('has')
                    ->with(SteamProvider::class)
                    ->andReturn(true)
                ->shouldReceive('has')
                    ->with(StaticDataProvider::class)
                    ->andReturn(false)
                ->shouldReceive('get')
                    ->with(SteamProvider::class)
                    ->andReturn(new SteamProvider)
                ->getMock()
        );
    }

    public static function createSession(Porter $porter): Promise
    {
        if (self::$savedSession) {
            return self::$savedSession;
        }

        return self::$savedSession = call(static function () use ($porter): \Generator {
            if (isset($_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD'])) {
                return yield CuratorSession::create($porter, $_SERVER['STEAM_USER'], $_SERVER['STEAM_PASSWORD']);
            }

            return yield CuratorSession::createFromCookie(
                SecureLoginCookie::create($_SERVER['STEAM_COOKIE']),
                $porter
            );
        });
    }
}
