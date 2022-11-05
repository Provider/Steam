<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Http\Client\Cookie\CookieJar;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

abstract class CuratorResource implements ProviderResource
{
    public function __construct(protected CuratorSession $session, protected int $curatorId)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    abstract protected function getSource(): DataSource;

    public function fetch(ImportConnector $connector): \Iterator
    {
        $baseConnector = $connector->findBaseConnector();
        if (!$baseConnector instanceof HttpConnector) {
            throw new \InvalidArgumentException('Unexpected connector type.');
        }

        $this->applySessionCookies($baseConnector->getCookieJar());

        $response = $connector->fetch($this->getSource());

        yield from $this->emitResponses($response);
    }

    protected function emitResponses(HttpResponse $response): \Generator
    {
        $json = \json_decode($response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        yield $json;
    }

    private function applySessionCookies(CookieJar $cookieJar): void
    {
        $cookieJar->store($this->session->getSecureLoginCookie());
        $cookieJar->store($this->session->getStoreSessionCookie());
    }
}
