<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Artax\FormBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class PatchCuratorListAppOrder extends CuratorResource
{
    private $listId;

    private $appIds;

    public function __construct(CuratorSession $session, int $curatorId, string $listId, array $appIds)
    {
        parent::__construct($session, $curatorId);

        $this->listId = $listId;
        $this->appIds = $appIds;
    }

    protected function getSource(): DataSource
    {
        $body = new FormBody;
        $body->addFields([
            'listid' => $this->listId,
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);

        foreach ($this->appIds as $appId) {
            $body->addField('appids', (string)$appId);
        }

        return (new AsyncHttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxupdatesortorder/")
        ))
            ->setMethod('POST')
            ->setBody($body)
        ;
    }
}
