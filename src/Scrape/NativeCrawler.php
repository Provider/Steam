<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Symfony\Component\DomCrawler\Crawler;

final class NativeCrawler extends Crawler
{
    public function __construct(
        \DOMNode|\DOMNodeList|array|string|null $node = null,
        ?string $uri = null,
        ?string $baseHref = null,
    ) {
        parent::__construct($node, $uri, $baseHref, false);
    }
}
