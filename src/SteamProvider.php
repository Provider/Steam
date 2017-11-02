<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam;

use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Provider\Provider;

final class SteamProvider implements Provider
{
    private const STEAMWORKS_API_URL = 'https://api.steampowered.com';
    private const STORE_API_URL = 'http://store.steampowered.com';

    private $connector;

    public function __construct(HttpConnector $connector = null)
    {
        $this->connector = $connector ?: new HttpConnector;
    }

    public static function buildSteamworksApiUrl(string $url)
    {
        return self::STEAMWORKS_API_URL . $url;
    }

    public static function buildStoreApiUrl(string $url)
    {
        return self::STORE_API_URL . $url;
    }

    public function getConnector()
    {
        return $this->connector;
    }
}
