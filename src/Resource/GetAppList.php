<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Options\EncapsulatedOptions;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * @see https://partner.steamgames.com/doc/webapi/ISteamApps#GetAppList
 */
final class GetAppList implements ProviderResource
{
    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector, EncapsulatedOptions $options = null)
    {
        return new \ArrayIterator(
            \json_decode($connector->fetch('/ISteamApps/GetAppList/v2/'), true)['applist']['apps']
        );
    }
}
