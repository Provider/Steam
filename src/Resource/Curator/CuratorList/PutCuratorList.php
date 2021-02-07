<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\RequestBody;
use ScriptFUSION\Porter\Connector\AsyncDataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\SingleRecordResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Creates a curator list if list ID is not set, otherwise updates the curator list specified by the list ID.
 */
final class PutCuratorList extends CuratorResource implements SingleRecordResource
{
    private $list;

    public function __construct(CuratorSession $session, int $curatorId, CuratorList $list)
    {
        parent::__construct($session, $curatorId);

        $this->list = $list;
    }

    protected function getSource(): AsyncDataSource
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
            // TODO: Remove or set to "none" when Valve get their shit together.
            'sale_event_gid' => '3033706120464786490',
        ]);

        return $body;
    }
}
