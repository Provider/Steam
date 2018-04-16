<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam;

use ScriptFUSION\Porter\Connector\AsyncConnector;
use ScriptFUSION\Porter\Connector\Connector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Provider\AsyncProvider;
use ScriptFUSION\Porter\Provider\Provider;

final class SteamProvider implements Provider, AsyncProvider
{
    private const STEAMWORKS_API_URL = 'https://api.steampowered.com';
    private const STORE_API_URL = 'http://store.steampowered.com';

    private $connector;

    private $asyncConnector;

    public function __construct(Connector $connector = null, AsyncConnector $asyncConnector = null)
    {
        $this->connector = $connector ?: new HttpConnector;
        $this->asyncConnector = $asyncConnector ?: new AsyncHttpConnector;
    }

    public static function buildSteamworksApiUrl(string $url): string
    {
        return self::STEAMWORKS_API_URL . $url;
    }

    public static function buildStoreApiUrl(string $url): string
    {
        return self::STORE_API_URL . $url;
    }

    public function getConnector(): Connector
    {
        return $this->connector;
    }

    public function getAsyncConnector(): AsyncConnector
    {
        return $this->asyncConnector;
    }
}
