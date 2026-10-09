<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Http\Cookie\CookieAttributes;
use Amp\Http\Cookie\ResponseCookie;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Scrapes the Steam store page for App details.
 *
 * Anonymous scraping forces USD prices via the undocumented `cc` parameter. However, since 2026/10/09, adult-only
 * content requires a logged-in user; when a store session is provided, prices reflect the login account's store
 * currency, ignoring the `cc` parameter.
 */
final class ScrapeAppDetails implements ProviderResource, SingleRecordResource, Url
{
    public function __construct(
        private readonly int $appId,
        private readonly ?StoreSession $storeSession = null,
    ) {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $this->configureAsyncOptions($connector->findBaseConnector());

        /** @var HttpResponse $response */
        $this->validateResponse($response = $connector->fetch(
            (new HttpDataSource($this->getUrl()))
        ));

        yield AppDetailsParser::tryParseStorePage($response->getBody());
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

    private function configureAsyncOptions(HttpConnector $connector): void
    {
        $connector->getOptions()
            // We want to capture redirects so do not follow them automatically.
            ->setMaxRedirects(0)
        ;

        $cookies = $connector->getCookieJar();
        $cookieAttributes = CookieAttributes::default()->withDomain(SteamProvider::STORE_DOMAIN);
        // Enable age-restricted content.
        $cookies->store(new ResponseCookie('birthtime', '0', $cookieAttributes));
        // Enable mature content.
        $cookies->store(new ResponseCookie('wants_mature_content', '1', $cookieAttributes));

        if ($this->storeSession !== null) {
            // Authenticate as the login user, unlocking Adult Only Sexual Content. Note prices will
            // reflect the login account's store currency instead of USD (the `cc` parameter is ignored).
            // Only curator pages need the store session cookie, so the login cookie suffices here.
            $cookies->store($this->storeSession->getSecureLoginCookie());
        }
    }
}
