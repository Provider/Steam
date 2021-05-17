<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\User;

use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\UserProfileParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use Symfony\Component\DomCrawler\Crawler;

final class ScrapeUserProfile implements AsyncResource, SingleRecordResource
{
    private $steamId;

    public function __construct(\SteamID $steamID)
    {
        $this->steamId = $steamID;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        return new Producer(function (\Closure $emit) use ($connector): \Generator {
            $response = yield $connector->fetchAsync(
                new AsyncHttpDataSource("https://steamcommunity.com/profiles/{$this->steamId->ConvertToUInt64()}")
            );

            yield $emit(UserProfileParser::parse(new Crawler($response->getBody())));
        });
    }
}
