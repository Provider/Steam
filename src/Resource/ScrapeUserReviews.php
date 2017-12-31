<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Options\EncapsulatedOptions;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\UserReviewsParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeUserReviews implements ProviderResource, Url
{
    private $profileUrl;

    public function __construct(string $profileUrl)
    {
        $this->profileUrl = $profileUrl;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector, EncapsulatedOptions $options = null): \Iterator
    {
        $next = null;

        do {
            $html = $connector->fetch($this->getUrl($next ? $next->attr('href') : ''), $options)->getBody();
            $crawler = new Crawler($html);

            yield from UserReviewsParser::parse($crawler);

            $next = $crawler->filter('.workshopBrowsePagingControls a.pagebtn:last-of-type')->first();
        } while (\count($next));
    }

    public function getUrl(string $page = null): string
    {
        return "$this->profileUrl/reviews/$page";
    }
}
