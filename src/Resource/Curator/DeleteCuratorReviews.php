<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\FormBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorReviews extends CuratorResource
{
    private $appIds;

    public function __construct(CuratorSession $session, int $curatorId, array $appIds)
    {
        parent::__construct($session, $curatorId);

        $this->appIds = $appIds;
    }

    protected function getUrl(): string
    {
        return SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxupdatemultiplecurations/");
    }

    protected function getSource(): DataSource
    {
        $body = new FormBody;

        $body->addFields([
            'delete' => 1,
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);

        foreach ($this->appIds as $appId) {
            $body->addField('appids', $appId);
        }

        return (new AsyncHttpDataSource($this->getUrl()))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
