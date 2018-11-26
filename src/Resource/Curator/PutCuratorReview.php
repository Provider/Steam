<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\FormBody;
use ScriptFUSION\Porter\Net\Http\AsyncHttpOptions;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Creates or updates an existing app review.
 */
final class PutCuratorReview extends CuratorResource
{
    private $appId;
    private $reviewBody;
    private $recommendationState;
    private $linkUrl;

    public function __construct(
        CuratorSession $session,
        int $curatorId,
        int $appId,
        string $reviewBody,
        RecommendationState $recommendationState,
        string $linkUrl = ''
    ) {
        parent::__construct($session, $curatorId);

        $this->appId = $appId;
        $this->reviewBody = $reviewBody;
        $this->recommendationState = $recommendationState;
        $this->linkUrl = $linkUrl;
    }

    protected function getUrl(): string
    {
        return SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxcreatereview/");
    }

    protected function augmentOptions(AsyncHttpOptions $options): void
    {
        parent::augmentOptions($options);

        $options->setMethod('POST')->setBody($body = new FormBody);

        $body->addFields([
            'appid' => $this->appId,
            'blurb' => $this->reviewBody,
            'link_url' => $this->linkUrl,
            'recommendation_state' => $this->recommendationState->toInt(),
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);
    }
}
