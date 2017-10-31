<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpOptions;
use ScriptFUSION\Porter\Options\EncapsulatedOptions;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Scrapes the Steam store page for App details.
 */
final class ScrapeAppDetails implements ProviderResource
{
    private $appId;

    private $options;

    public function __construct(int $appId)
    {
        $this->appId = $appId;

        // We want to capture redirects so do not follow them automatically.
        $this->options = (new HttpOptions)->setFollowLocation(false);
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector, EncapsulatedOptions $options = null): \Iterator
    {
        // We force the country to US for consistency and easier date parsing using the undocumented cc parameter.
        $response = $connector->fetch($url = "http://store.steampowered.com/app/$this->appId/?cc=us", $this->options);

        // Assume a redirect indicates an invalid ID.
        if ($response->hasHeader('Location')) {
            throw new InvalidAppIdException(
                "Application ID \"$this->appId\" is redirecting to \"{$response->getHeader('Location')[0]}\"."
            );
        }

        yield AppDetailsParser::parseStorePage($response->getBody());
    }
}
