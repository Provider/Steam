<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * @see https://steamapi.xpaw.me/IStoreBrowseService#GetItems
 */
final readonly class GetAppAssets implements ProviderResource
{
    public function __construct(private array $appIds)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $json = \json_encode([
            'context' => [
                // Do not fetch unrelated fields.
                'country_code' => 'xx',
            ],
            'data_request' => [
                'include_assets' => true,
            ],
            'ids' => array_map(fn ($appid) =>  ['appid' => $appid], $this->appIds),
        ], JSON_THROW_ON_ERROR);

        $response = $connector->fetch(new HttpDataSource(
            SteamProvider::buildSteamworksApiUrl('/IStoreBrowseService/GetItems/v1')
                . '?input_json=' . urlencode($json)
        ));

        yield from \json_decode($response->getBody(), true, flags: JSON_THROW_ON_ERROR)['response']['store_items'];
    }

}
