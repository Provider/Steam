<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Fixture;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Options\EncapsulatedOptions;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\StaticDataProvider;
use ScriptFUSION\Porter\Provider\Steam\Scrape\AppDetailsParser;

class ScrapeDiscountApp implements ProviderResource
{
    public function getProviderClassName(): string
    {
        return StaticDataProvider::class;
    }

    public function fetch(ImportConnector $connector, EncapsulatedOptions $options = null): \Iterator
    {
        yield AppDetailsParser::parseStorePage(file_get_contents(__DIR__ . '/discounted.html'));
    }
}
