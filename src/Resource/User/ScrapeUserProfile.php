<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\User;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\UserProfileParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use Symfony\Component\DomCrawler\Crawler;

final class ScrapeUserProfile implements ProviderResource, SingleRecordResource
{
    public function __construct(private readonly \SteamID $steamId)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $response = $connector->fetch(
            new AsyncHttpDataSource("https://steamcommunity.com/profiles/{$this->steamId->ConvertToUInt64()}")
        );

        yield UserProfileParser::parse(new Crawler($response->getBody()));
    }
}
