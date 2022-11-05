<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Http\Client\Body\FormBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorReviews extends CuratorResource
{
    public function __construct(CuratorSession $session, int $curatorId, private readonly array $appIds)
    {
        parent::__construct($session, $curatorId);
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

        return (new HttpDataSource($this->getUrl()))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
