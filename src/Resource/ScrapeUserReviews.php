<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\UsersReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\Scrape\NativeCrawler;
use ScriptFUSION\Porter\Provider\Steam\Scrape\UserReviewsParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Scrapes all reviews posted by a user from their profile.
 */
final class ScrapeUserReviews implements ProviderResource, Url
{
    public function __construct(private readonly string $profileUrl)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        /** @var Crawler $crawler */
        $pages = $this->fetchPages($connector, $crawler);

        // Force Crawler creation.
        $pages->current();
        $count = $crawler->filter('.review_stat')->first()->filter('.giantNumber')->text();
        $avatar = preg_replace('[_medium(?=\.jpg$)]', '', $crawler->filter('.playerAvatar > img')->attr('src'));

        return new UsersReviewsRecords($pages, +$count, $avatar, $this);
    }

    private function fetchPages(ImportConnector $connector, ?Crawler &$crawler): \Generator
    {
        $next = null;

        do {
            $html = $connector->fetch(
                new HttpDataSource($this->getUrl($next ? $next->attr('href') : ''))
            )->getBody();
            $crawler = new NativeCrawler($html);

            yield from UserReviewsParser::parse($crawler);

            $next = $crawler->filter('.workshopBrowsePagingControls a.pagebtn:last-of-type')->first();
        } while (\count($next));
    }

    public function getUrl(?string $page = null): string
    {
        return "$this->profileUrl/reviews/$page";
    }
}
