<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\FormBody;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorReviews implements AsyncResource
{
    private $session;

    private $curatorId;

    private $appIds;

    public function __construct(CuratorSession $session, string $curatorId, array $appIds)
    {
        $this->session = $session;
        $this->curatorId = $curatorId;
        $this->appIds = $appIds;
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

            $cookies = $baseConnector->getOptions()
                ->setMethod('POST')
                ->setBody($body = new FormBody)
                ->getCookieJar()
            ;

            $cookies->store($this->session->getSecureLoginCookie());
            $cookies->store($this->session->getStoreSessionCookie());

            $body->addFields([
                'delete' => 1,
                'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
            ]);

            foreach ($this->appIds as $appId) {
                $body->addField('appids', $appId);
            }

            $response = yield $connector->fetchAsync(SteamProvider::buildStoreApiUrl(
                "/curator/$this->curatorId/admin/ajaxupdatemultiplecurations/"
            ));

            yield $emit(\json_decode((string)$response, true));
        });
    }

}
