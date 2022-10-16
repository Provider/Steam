<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use ScriptFUSION\Porter\Connector\AsyncDataSource;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class GetCuratorReviews extends CuratorResource
{
    protected function getSource(): AsyncDataSource
    {
        return new AsyncHttpDataSource(SteamProvider::buildStoreApiUrl(
            "/curator/$this->curatorId/admin/ajaxgetrecommendations/?count=0x7FFFFFFF"
        ));
    }

    protected function emitResponses(HttpResponse $response): \Generator
    {
        $json = \json_decode((string)$response, true);

        foreach ($json['recommendations'] as $recommendation) {
            yield $recommendation;
        }
    }
}
