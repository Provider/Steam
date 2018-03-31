<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Scrapes the Steam store page for App details.
 */
final class ScrapeAppDetails implements ProviderResource, Url
{
    private $appId;

    public function __construct(int $appId)
    {
        $this->appId = $appId;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $this->configureOptions($connector->getWrappedConnector());

        $response = $connector->fetch($this->getUrl());

        // Assume a redirect indicates an invalid ID.
        if ($response->hasHeader('Location')) {
            throw new InvalidAppIdException(
                "Application ID \"$this->appId\" is redirecting to \"{$response->getHeader('Location')[0]}\"."
            );
        }

        yield AppDetailsParser::parseStorePage($response->getBody());
    }

    public function getUrl(): string
    {
        // Force the country to US, for consistency and easier date parsing, with the undocumented 'cc' parameter.
        return "http://store.steampowered.com/app/$this->appId/?cc=us";
    }

    private function configureOptions(HttpConnector $connector): void
    {
        $connector->getOptions()
            // We want to capture redirects so do not follow them automatically.
            ->setFollowLocation(false)
            // Enable age-restricted and mature content.
            ->addHeader('Cookie: birthtime=0; mature_content=1')
        ;
    }
}
