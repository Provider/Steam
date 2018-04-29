<?php

namespace ScriptFUSIONTest\Porter\Provider\Steam;

use Amp\Artax\Cookie\Cookie;
use Psr\Container\ContainerInterface;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\StaticDataProvider;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSION\StaticClass;

final class FixtureFactory
{
    use StaticClass;

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

    public static function createSecureLoginCookie(): SecureLoginCookie
    {
        return new SecureLoginCookie(
            new Cookie(
                'steamLoginSecure',
                $_SERVER['STEAM_SECURE_LOGIN_COOKIE'],
                null,
                '/',
                SteamProvider::STORE_DOMAIN,
                true
            )
        );
    }
}
