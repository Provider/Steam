<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\Cookie\CookieJar;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpOptions;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

abstract class CuratorResource implements AsyncResource
{
    protected $session;

    protected $curatorId;

    public function __construct(CuratorSession $session, string $curatorId)
    {
        $this->session = $session;
        $this->curatorId = $curatorId;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    abstract protected function getUrl(): string;

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        return new Producer(function (\Closure $emit) use ($connector): \Generator {
            $baseConnector = $connector->findBaseConnector();
            if (!$baseConnector instanceof AsyncHttpConnector) {
                throw new \InvalidArgumentException('Unexpected connector type.');
            }

            $this->augmentOptions($baseConnector->getOptions());

            $response = yield $connector->fetchAsync($this->getUrl());

            yield from $this->emitResponses($emit, $response);
        });
    }

    protected function augmentOptions(AsyncHttpOptions $options): void
    {
        $this->applySessionCookies($options->getCookieJar());
    }

    protected function emitResponses(\Closure $emit, HttpResponse $response): \Generator
    {
        yield $emit(\json_decode($response->getBody(), true));
    }

    private function applySessionCookies(CookieJar $cookieJar): void
    {
        $cookieJar->store($this->session->getSecureLoginCookie());
        $cookieJar->store($this->session->getStoreSessionCookie());
    }
}
