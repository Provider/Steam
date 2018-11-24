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

/**
 * Creates or updates an existing app review.
 */
final class PutCuratorReview implements AsyncResource
{
    private $session;
    private $curatorId;
    private $appId;
    private $reviewBody;
    private $recommendationState;
    private $linkUrl;

    public function __construct(
        CuratorSession $session,
        string $curatorId,
        string $appId,
        string $reviewBody,
        RecommendationState $recommendationState,
        string $linkUrl = ''
    ) {
        $this->session = $session;
        $this->curatorId = $curatorId;
        $this->appId = $appId;
        $this->reviewBody = $reviewBody;
        $this->recommendationState = $recommendationState;
        $this->linkUrl = $linkUrl;
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

            $this->session->apply(
                $baseConnector->getOptions()
                    ->setMethod('POST')
                    ->setBody($body = new FormBody)
                    ->getCookieJar()
            );

            $body->addFields([
                'appid' => $this->appId,
                'blurb' => $this->reviewBody,
                'link_url' => $this->linkUrl,
                'recommendation_state' => $this->recommendationState->toInt(),
                'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
            ]);

            $response = yield $connector->fetchAsync(SteamProvider::buildStoreApiUrl(
                "/curator/$this->curatorId/admin/ajaxcreatereview/"
            ));

            yield $emit(\json_decode((string)$response, true));
        });
    }
}
