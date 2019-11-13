<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Artax\FormBody;
use Amp\Artax\RequestBody;
use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Creates a curator list if list ID is not set, otherwise updates the curator list specified by the list ID.
 */
final class PutCuratorList extends CuratorResource
{
    private $list;

    public function __construct(CuratorSession $session, int $curatorId, CuratorList $list)
    {
        parent::__construct($session, $curatorId);

        $this->list = $list;
    }

    protected function getSource(): DataSource
    {
        return (new AsyncHttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxeditlist/")
        ))
            ->setMethod('POST')
            ->setBody($this->toFormBody($this->list))
        ;
    }

    private function toFormBody(CuratorList $list): RequestBody
    {
        $body = new FormBody;

        $body->addFields([
            'blurb' => $list->getDescription(),
            'listid' => $list->getListId(),
            'order' => 'specified',
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
            'showtitleanddesc' => 1,
            'state' => $list->getTitle() === '' ? 0 : 1,
            'title' => $list->getTitle(),
            'title_blurb_locs' => '{}',
            'type' => 2,
        ]);

        return $body;
    }
}
