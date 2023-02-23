<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam;

use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Mockery\MockInterface;
use ScriptFUSION\Porter\Connector\Connector;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\StaticClass;

final class MockFactory
{
    use StaticClass;

    public static function mockConnectorResponse(Connector|MockInterface $connector, string $response): void
    {
        $connector->expects('fetch')->andReturn(new HttpResponse(new Response(
            '1.0',
            2,
            null,
            [],
            new ReadableBuffer($response),
            new Request('foo'),
        )));
    }
}
