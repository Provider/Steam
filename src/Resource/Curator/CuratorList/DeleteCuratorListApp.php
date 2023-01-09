<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Http\Client\Body\FormBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorListApp extends CuratorResource implements SingleRecordResource
{
    public function __construct(
        CuratorSession $session,
        int $curatorId,
        private readonly string $listId,
        private readonly int $appId,
    ) {
        parent::__construct($session, $curatorId);
    }

    protected function getSource(): DataSource
    {
        $body = new FormBody;
        $body->addFields([
            'appid' => (string)$this->appId,
            'listid' => $this->listId,
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);

        return (new HttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxremovefromlist/")
        ))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
