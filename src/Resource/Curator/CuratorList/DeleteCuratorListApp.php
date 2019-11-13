<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Artax\FormBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorListApp extends CuratorResource
{
    private $listId;

    private $appId;

    public function __construct(CuratorSession $session, int $curatorId, string $listId, int $appId)
    {
        parent::__construct($session, $curatorId);

        $this->listId = $listId;
        $this->appId = $appId;
    }

    protected function getSource(): DataSource
    {
        $body = new FormBody;
        $body->addFields([
            'appid' => $this->appId,
            'listid' => $this->listId,
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);

        return (new AsyncHttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/{$this->curatorId}/admin/ajaxremovefromlist/")
        ))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
