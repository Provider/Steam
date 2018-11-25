<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class GetCuratorReviews extends CuratorResource
{
    protected function getUrl(): string
    {
        return SteamProvider::buildStoreApiUrl(
            "/curator/$this->curatorId/admin/ajaxgetrecommendations/?count=0x7FFFFFFF"
        );
    }

    protected function emitResponses(\Closure $emit, HttpResponse $response): \Generator
    {
        $json = \json_decode((string)$response, true);

        foreach ($json['recommendations'] as $recommendation) {
            yield $emit($recommendation);
        }
    }
}
