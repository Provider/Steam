<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Deferred;
use Amp\Http\Cookie\RequestCookie;
use Amp\Http\Cookie\ResponseCookie;
use Amp\Iterator;
use Amp\Producer;
use League\Uri\Http;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncSteamStoreSessionRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\StoreSessionCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class CreateSteamStoreSession implements AsyncResource
{
    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        $sessionCookie = new Deferred;

        return new AsyncSteamStoreSessionRecord(
            new Producer(static function () use ($connector, $sessionCookie): \Generator {
                try {
                    $baseConnector = $connector->findBaseConnector();
                    if (!$baseConnector instanceof AsyncHttpConnector) {
                        throw new \InvalidArgumentException('Unexpected connector type.');
                    }

                    yield $connector->fetchAsync(new AsyncHttpDataSource(SteamProvider::buildStoreApiUrl('/')));
                } catch (\Throwable $throwable) {
                    $sessionCookie->fail($throwable);

                    throw $throwable;
                }

                $steamSession = current(array_filter(
                    yield $baseConnector->getCookieJar()->get(Http::createFromString(SteamProvider::STORE_API_URL)),
                    static function (RequestCookie $cookie) {
                        return $cookie->getName() === 'sessionid';
                    }
                ));

                assert($steamSession);

                $sessionCookie->resolve(StoreSessionCookie::create($steamSession->getValue()));
            }),
            $sessionCookie->promise(),
            $this
        );
    }
}
