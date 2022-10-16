<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use ScriptFUSION\Porter\Connector\AsyncDataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class GetCuratorLists extends CuratorResource
{
    protected function getSource(): AsyncDataSource
    {
        return new AsyncHttpDataSource(
            SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/ajaxgetlists/?all=1")
        );
    }

    protected function emitResponses(HttpResponse $response): \Generator
    {
        $json = \json_decode((string)$response, true);

        foreach ($json['list_details'] as $list) {
            yield $list;
        }
    }
}
