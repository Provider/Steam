<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorResource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class GetCuratorLists extends CuratorResource
{
    protected function getUrl(): string
    {
        return SteamProvider::buildStoreApiUrl("/curator/$this->curatorId/ajaxgetlists/");
    }

    protected function emitResponses(\Closure $emit, HttpResponse $response): \Generator
    {
        $json = \json_decode((string)$response, true);

        foreach ($json['list_details'] as $list) {
            yield $emit($list);
        }
    }
}
