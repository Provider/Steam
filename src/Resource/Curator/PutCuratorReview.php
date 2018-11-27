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
    private $review;

    public function __construct(
        CuratorSession $session,
        int $curatorId,
        CuratorReview $review
    ) {
        parent::__construct($session, $curatorId);

        $this->review = $review;
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
            'appid' => $this->review->getAppId(),
            'blurb' => $this->review->getBody(),
            'link_url' => $this->review->getUrl(),
            'recommendation_state' => $this->review->getRecommendationState()->toInt(),
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);
    }
}
