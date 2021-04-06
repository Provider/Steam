<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\Resource\SessionResource;

abstract class CuratorResource extends SessionResource
{
    protected $curatorId;

    public function __construct(CuratorSession $session, int $curatorId)
    {
        $this->setSession($session);
        $this->curatorId = $curatorId;
    }

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
}
