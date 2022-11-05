<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\DeferredFuture;
use Amp\Http\Cookie\ResponseCookie;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncSteamStoreSessionRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\StoreSessionCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class CreateSteamStoreSession implements ProviderResource
{
    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $sessionCookie = new DeferredFuture();

        return new AsyncSteamStoreSessionRecord(
            (static function () use ($connector, $sessionCookie): \Generator {
                try {
                    $baseConnector = $connector->findBaseConnector();
                    if (!$baseConnector instanceof HttpConnector) {
                        throw new \InvalidArgumentException('Unexpected connector type.');
                    }

                    $connector->fetch(new HttpDataSource(SteamProvider::buildStoreApiUrl('/')));
                } catch (\Throwable $throwable) {
                    $sessionCookie->error($throwable);

                    throw $throwable;
                }

                $steamSession = current(array_filter(
                    $baseConnector->getCookieJar()->getAll(),
                    static function (ResponseCookie $cookie) {
                        return $cookie->getName() === 'sessionid';
                    }
                ));

                assert($steamSession);

                $sessionCookie->complete(new StoreSessionCookie($steamSession));

                yield [];
            })(),
            $sessionCookie->getFuture(),
            $this
        );
    }
}
