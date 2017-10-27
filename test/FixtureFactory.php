<?php

namespace ScriptFUSIONTest\Porter\Provider\Steam;

use Psr\Container\ContainerInterface;
use ScriptFUSION\Porter\Porter;
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
                ->shouldReceive('get')
                    ->with(SteamProvider::class)
                    ->andReturn(new SteamProvider)
                ->getMock()
        );
    }
}
