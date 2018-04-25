<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Artax\Cookie\Cookie;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;
use ScriptFUSION\Porter\Provider\Steam\Scrape\InvalidMarkupException;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Scrapes the Steam store page for App details.
 */
final class ScrapeAppDetails implements ProviderResource, AsyncResource, Url
{
    private const DOMAIN = 'store.steampowered.com';

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
        $this->configureOptions($connector->findBaseConnector());

        $this->validateResponse($response = $connector->fetch($this->getUrl()));

        yield AppDetailsParser::tryParseStorePage($response->getBody());
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        $this->configureAsyncOptions($connector->findBaseConnector());

        return new Producer(function (\Closure $emit) use ($connector) {
            /** @var HttpResponse $response */
            $this->validateResponse($response = yield $connector->fetchAsync($this->getUrl()));

            yield $emit(AppDetailsParser::tryParseStorePage($response->getBody()));
        });
    }

    private function validateResponse(HttpResponse $response): void
    {
        // Assume a redirect indicates an invalid ID.
        if ($response->hasHeader('Location')) {
            throw new InvalidAppIdException(
                "Application ID \"$this->appId\" is redirecting to \"{$response->getHeader('Location')[0]}\"."
            );
        }
    }

    public function getUrl(): string
    {
        // Force the country to US, for consistency and easier date parsing, with the undocumented 'cc' parameter.
        return SteamProvider::buildStoreApiUrl("/app/$this->appId/?cc=us");
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

    private function configureAsyncOptions(AsyncHttpConnector $connector): void
    {
        $options = $connector->getOptions()
            // Do not follow redirects.
            ->setMaxRedirects(0)
        ;

        $cookies = $options->getCookieJar();
        // Enable age-restricted content.
        $cookies->store(new Cookie('birthtime', '0', null, null, self::DOMAIN));
        // Enable mature content.
        $cookies->store(new Cookie('mature_content', '1', null, null, self::DOMAIN));
    }
}
