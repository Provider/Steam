<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class GetCuratorReviews extends CuratorResource
{
    protected function getSource(): DataSource
    {
        return new HttpDataSource(SteamProvider::buildStoreApiUrl(
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
