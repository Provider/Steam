<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use ScriptFUSION\Retry\ExceptionHandler\ExponentialBackoffExceptionHandler;
use function ScriptFUSION\Retry\retry;

/**
 * @see https://partner.steamgames.com/doc/webapi/ISteamApps#GetAppList
 * @see https://steamapi.xpaw.me/#IStoreService
 */
final class GetAppList implements ProviderResource, Url
{
    private const APP_LIST_PATH = '/ISteamApps/GetAppList/v2/';
    private const PAGINATED_APP_LIST_PATH = '/IStoreService/GetAppList/v1/?max_results=50000&include_dlc=1'
        . '&include_software=1&include_videos=1&include_hardware=1';

    private $apiKey;

    public function __construct(string $apiKey = null)
    {
        $this->apiKey = $apiKey;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        do {
            yield from retry(
                10,
                function () use ($connector, &$lastId): array {
                    $json = \json_decode(
                        (string)$connector->fetch(new HttpDataSource(
                            $this->getUrl() . (isset($lastId) ? "&last_appid=$lastId" : '')
                        )),
                        true
                    );

                    $apps = $json['applist']['apps'] ?? $json['response']['apps'] ?? null;

                    // App list is empty about 20% of the time, seemingly more often on CI, so counting is important.
                    if ($json === null || !isset($apps) || !count($apps)) {
                        throw new FetchAppListException('App list was empty!');
                    }

                    $lastId = $json['response']['last_appid'] ?? null;

                    return $apps;
                },
                new ExponentialBackoffExceptionHandler
            );
        } while ($lastId);
    }

    public function getUrl(): string
    {
        return SteamProvider::buildSteamworksApiUrl(
            $this->apiKey ? self::PAGINATED_APP_LIST_PATH . "&key=$this->apiKey" : self::APP_LIST_PATH
        );
    }
}
