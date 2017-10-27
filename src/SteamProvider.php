<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam;

use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Provider\Provider;

final class SteamProvider implements Provider
{
    private const BASE_URL = 'https://api.steampowered.com';

    private $connector;

    public function __construct(HttpConnector $connector = null)
    {
        $this->connector = $connector ?: new HttpConnector;
        $this->connector->setBaseUrl(self::BASE_URL);
    }

    public function getConnector()
    {
        return $this->connector;
    }
}
