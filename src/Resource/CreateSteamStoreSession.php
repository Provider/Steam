<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Deferred;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
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

                    $options = $baseConnector->getOptions()->setDiscardBody(true);

                    yield $connector->fetchAsync(SteamProvider::buildStoreApiUrl('/'));
                } catch (\Throwable $throwable) {
                    $sessionCookie->fail($throwable);

                    throw $throwable;
                }

                $sessionCookie->resolve(
                    new StoreSessionCookie(
                        $options->getCookieJar()->get(SteamProvider::STORE_DOMAIN, '', 'sessionid')[0]
                    )
                );
            }),
            $sessionCookie->promise(),
            $this
        );
    }
}
