<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Fixture;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Options\EncapsulatedOptions;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\StaticDataProvider;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;

class ScrapeAppFixture implements ProviderResource
{
    private $fixture;

    public function __construct(string $fixture)
    {
        $this->fixture = $fixture;
    }

    public function getProviderClassName(): string
    {
        return StaticDataProvider::class;
    }

    public function fetch(ImportConnector $connector, EncapsulatedOptions $options = null): \Iterator
    {
        yield AppDetailsParser::tryParseStorePage(file_get_contents(__DIR__ . "/$this->fixture"));
    }
}
