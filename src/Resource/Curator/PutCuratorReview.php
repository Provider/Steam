<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Http\Client\Form;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Creates or updates an existing app review.
 */
final class PutCuratorReview extends CuratorResource implements SingleRecordResource
{
    private $review;

    public function __construct(CuratorSession $session, int $curatorId, CuratorReview $review)
    {
        parent::__construct($session, $curatorId);

        $this->review = $review;
    }

    protected function getSource(): DataSource
    {
        $body = new Form;
        foreach ([
            'appid' => (string)$this->review->getAppId(),
            'blurb' => $this->review->getBody(),
            'link_url' => $this->review->getUrl(),
            'recommendation_state' => (string)$this->review->getRecommendationState()->toInt(),
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ] as $name => $value) {
            $body->addField($name, $value);
        }

        return (new HttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxcreatereview/")
        ))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
