<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * @see https://partner.steamgames.com/doc/webapi/ISteamApps#GetAppList
 */
final class GetAppList implements ProviderResource, StaticUrl
{
    private const APP_LIST_URL = '/ISteamApps/GetAppList/v2/';

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $tries = -1;

        retry:
        if (++$tries === 10) {
            throw new FetchAppListException("Could not fetch app list after $tries attempts.");
        }

        $json = \json_decode((string)$connector->fetch(new HttpDataSource(self::getUrl())), true);

        if (isset($json['applist']['apps'])) {
            $apps = $json['applist']['apps'];
        }

        // App list is empty about 20% of the time, seemingly more often on CI, so counting is important.
        if ($json === null || !isset($apps) || !count($apps)) {
            goto retry;
        }

        return new \ArrayIterator($apps);
    }

    public static function getUrl(): string
    {
        return SteamProvider::buildSteamworksApiUrl(self::APP_LIST_URL);
    }
}
