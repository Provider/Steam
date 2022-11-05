<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Http\Client\Body\FormBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorList extends CuratorResource implements SingleRecordResource
{
    private $listId;

    public function __construct(CuratorSession $session, int $curatorId, string $listId)
    {
        parent::__construct($session, $curatorId);

        $this->listId = $listId;
    }

    protected function getSource(): DataSource
    {
        $body = new FormBody;
        $body->addFields([
            'listid' => $this->listId,
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);

        return (new AsyncHttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxdeletelist/")
        ))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
