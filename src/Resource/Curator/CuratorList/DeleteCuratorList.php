<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use Amp\Artax\FormBody;
use ScriptFUSION\Porter\Net\Http\AsyncHttpOptions;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class DeleteCuratorList extends CuratorResource
{
    private $listId;

    public function __construct(CuratorSession $session, int $curatorId, string $listId)
    {
        parent::__construct($session, $curatorId);

        $this->listId = $listId;
    }

    protected function getUrl(): string
    {
        return SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/admin/ajaxdeletelist/");
    }

    protected function augmentOptions(AsyncHttpOptions $options): void
    {
        parent::augmentOptions($options);

        $options->setMethod('POST')->setBody($body = new FormBody);

        $body->addFields([
            'listid' => $this->listId,
            'sessionid' => $this->session->getStoreSessionCookie()->getValue(),
        ]);
    }
}
