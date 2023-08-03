<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam;

use ScriptFUSION\Porter\Connector\Connector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Provider\Provider;

final class SteamProvider implements Provider
{
    public const STORE_DOMAIN = 'store.steampowered.com';
    public const COMMUNITY_DOMAIN = 'steamcommunity.com';
    private const STEAMWORKS_API_URL = 'https://api.steampowered.com';
    private const STORE_API_URL = 'https://' . self::STORE_DOMAIN;
    private const COMMUNITY_URL = 'https://' . self::COMMUNITY_DOMAIN;

    private Connector $connector;

    public function __construct(Connector $connector = null)
    {
        $this->connector = $connector ?? new HttpConnector();
    }

    public static function buildSteamworksApiUrl(string $url): string
    {
        return self::STEAMWORKS_API_URL . $url;
    }

    public static function buildStoreApiUrl(string $url): string
    {
        return self::STORE_API_URL . $url;
    }

    public static function buildCommunityUrl(string $url): string
    {
        return self::COMMUNITY_URL . $url;
    }

    public function getConnector(): Connector
    {
        return $this->connector;
    }
}
