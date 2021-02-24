<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Http\Cookie\CookieAttributes;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;
use ScriptFUSION\Porter\Provider\Steam\Scrape\InvalidMarkupException;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use function Amp\call;
use function ScriptFUSION\Retry\retry;
use function ScriptFUSION\Retry\retryAsync;

/**
 * Scrapes the Steam store page for App details.
 */
final class ScrapeAppDetails implements ProviderResource, SingleRecordResource, AsyncResource, Url
{
    public const RETRIES = 5;

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

        yield retry(
            self::RETRIES,
            function () use ($connector) {
                $this->validateResponse($response = $connector->fetch(
                    (new HttpDataSource($this->getUrl()))
                        // Enable age-restricted and mature content.
                        ->addHeader('Cookie: birthtime=0; mature_content=1')
                ));

                return AppDetailsParser::tryParseStorePage($response->getBody());
            },
            self::createExceptionHandler()
        );
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        $this->configureAsyncOptions($connector->findBaseConnector());

        return new Producer(function (\Closure $emit) use ($connector): \Generator {
            yield $emit(retryAsync(
                self::RETRIES,
                function () use ($connector) {
                    return call(function () use ($connector) {
                        /** @var HttpResponse $response */
                        $this->validateResponse($response = yield $connector->fetchAsync(
                            (new AsyncHttpDataSource($this->getUrl()))
                        ));

                        return AppDetailsParser::tryParseStorePage($response->getBody());
                    });
                },
                self::createExceptionHandler()
            ));
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
        ;
    }

    private function configureAsyncOptions(AsyncHttpConnector $connector): void
    {
        $connector->getOptions()
            // Do not follow redirects.
            ->setMaxRedirects(0)
        ;

        $cookies = $connector->getCookieJar();
        $cookieAttributes = CookieAttributes::default()->withDomain(SteamProvider::STORE_DOMAIN);
        // Enable age-restricted content.
        $cookies->store(new ResponseCookie('birthtime', '0', $cookieAttributes));
        // Enable mature content.
        $cookies->store(new ResponseCookie('mature_content', '1', $cookieAttributes));
    }

    private static function createExceptionHandler(): \Closure
    {
        return static function (\Exception $exception): void {
            if (!$exception instanceof InvalidMarkupException) {
                throw $exception;
            }
        };
    }
}
