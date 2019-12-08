<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\Cookie\CookieJar;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\AsyncDataSource;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

abstract class CuratorResource implements AsyncResource
{
    protected $session;

    protected $curatorId;

    public function __construct(CuratorSession $session, int $curatorId)
    {
        $this->session = $session;
        $this->curatorId = $curatorId;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    abstract protected function getSource(): AsyncDataSource;

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        return new Producer(function (\Closure $emit) use ($connector): \Generator {
            $baseConnector = $connector->findBaseConnector();
            if (!$baseConnector instanceof AsyncHttpConnector) {
                throw new \InvalidArgumentException('Unexpected connector type.');
            }

            $this->applySessionCookies($baseConnector->getCookieJar());

            $response = yield $connector->fetchAsync($this->getSource());

            yield from $this->emitResponses($emit, $response);
        });
    }

    protected function emitResponses(\Closure $emit, HttpResponse $response): \Generator
    {
        $json = \json_decode($response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        yield $emit($json);
    }

    private function applySessionCookies(CookieJar $cookieJar): void
    {
        $cookieJar->store($this->session->getSecureLoginCookie());
        $cookieJar->store($this->session->getStoreSessionCookie());
    }
}
