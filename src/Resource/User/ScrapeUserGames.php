<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\User;

use Amp\Http\Client\Cookie\CookieJar;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\CommunitySession;
use ScriptFUSION\Porter\Provider\Steam\Resource\InvalidSessionException;
use ScriptFUSION\Porter\Provider\Steam\Resource\Url;
use ScriptFUSION\Porter\Provider\Steam\Scrape\NativeCrawler;
use ScriptFUSION\Porter\Provider\Steam\Scrape\UserGamesParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class ScrapeUserGames implements ProviderResource, Url
{
    public function __construct(private readonly CommunitySession $session, private readonly \SteamID $steamID)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $baseConnector = $connector->findBaseConnector();
        if (!$baseConnector instanceof HttpConnector) {
            throw new \InvalidArgumentException('Unexpected connector type.');
        }

        $this->applySessionCookies($baseConnector->getCookieJar());

        /** @var HttpResponse $response */
        $response = $connector->fetch(new HttpDataSource($this->getUrl()));

        if (($previousResponse = $response->getPrevious())
            && strpos($previousResponse->getHeader('location')[0], 'msg=loginfirst')
        ) {
            throw new InvalidSessionException('Session expired.');
        }

        yield from UserGamesParser::parse(new NativeCrawler($response->getBody()));
    }

    public function getUrl(): string
    {
        return SteamProvider::buildCommunityUrl("/profiles/{$this->steamID->RenderSteam3()}/games");
    }

    private function applySessionCookies(CookieJar $cookieJar): void
    {
        $cookieJar->store($this->session->getSecureLoginCookie());
    }
}
