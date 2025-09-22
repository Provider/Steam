<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use ScriptFUSION\Porter\Connector\DataSource;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class GetCuratorReviews extends CuratorResource
{
    private const COUNT = 800;

    private int $start = 0;

    protected function getSource(): DataSource
    {
        return new HttpDataSource(SteamProvider::buildStoreApiUrl(
            "/curator/$this->curatorId/admin/ajaxgetrecommendations/?start=$this->start&count=" . self::COUNT
        ));
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        do {
            $start = $this->start;

            yield from parent::fetch($connector);
        } while ($this->start > $start);
    }

    protected function emitResponses(HttpResponse $response): \Generator
    {
        $json = \json_decode((string)$response, true);

        foreach ($json['recommendations'] as $recommendation) {
            yield $recommendation;
        }

        if ($json['start'] + self::COUNT < $json['total_count']) {
            $this->start += self::COUNT;
        }
    }
}
