<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class ListCuratorReviews implements AsyncResource
{
    private $session;

    private $curatorId;

    public function __construct(CuratorSession $session, string $curatorId)
    {
        $this->session = $session;
        $this->curatorId = $curatorId;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        return new Producer(function (\Closure $emit) use ($connector): \Generator {
            $baseConnector = $connector->findBaseConnector();
            if (!$baseConnector instanceof AsyncHttpConnector) {
                throw new \InvalidArgumentException('Unexpected connector type.');
            }

            $cookies = $baseConnector->getOptions()->getCookieJar();

            $cookies->store($this->session->getSecureLoginCookie());
            $cookies->store($this->session->getStoreSessionCookie());

            $response = yield $connector->fetchAsync(SteamProvider::buildStoreApiUrl(
                "/curator/$this->curatorId/admin/ajaxgetrecommendations/?count=0x7FFFFFFF"
            ));

            $json = \json_decode((string)$response, true);

            foreach ($json['recommendations'] as $recommendation) {
                yield $emit($recommendation);
            }
        });
    }
}
