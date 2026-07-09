<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Async\Throttle\DualThrottle;
use ScriptFUSION\Async\Throttle\Throttle;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\NativeCrawler;
use ScriptFUSION\Porter\Provider\Steam\Scrape\StoreSearchParser;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Pages through the Steam global top-sellers, yielding each app's ID and final price.
 *
 * @see https://store.steampowered.com/search/?filter=globaltopsellers
 */
final class ScrapeGlobalTopSellers implements ProviderResource, Url
{
    /*
     * The store caps the number of results returned per request at 100, regardless of how large `count` is. Requesting
     * more is silently truncated, so 100 is the largest effective value before the server stops honouring it.
     */
    public const MAX_RESULTS = 100;

    /*
     * Maximum number of pages to retrieve. Steam begins rate-limiting the search/results endpoint shortly after this,
     * so we stop here to avoid upsetting the system.
     */
    public const MAX_PAGES = 30;

    private const FILTER = 'globaltopsellers';

    /*
     * The search/results endpoint imposes its own strict rate limit (throttling reliably kicks in after roughly 30
     * pages), so each request is paced by this throttle. This is unique to this endpoint and does not apply to other
     * Steam endpoints.
     */
    private const MAX_PER_SECOND = 5;
    private const MAX_CONCURRENCY = 1;

    private Throttle $throttle;

    public function __construct()
    {
        $this->throttle = new DualThrottle(self::MAX_PER_SECOND, self::MAX_CONCURRENCY);
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $start = 0;
        $page = 0;

        do {
            $source = new HttpDataSource($this->getUrl($start));

            /** @var HttpResponse $response */
            $response = $this->throttle->async($connector->fetch(...), $source)->await();

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException("Unexpected status code: {$response->getStatusCode()}.");
            }

            $json = json_decode($response->getBody(), true, flags: JSON_THROW_ON_ERROR);

            if (!isset($json['success']) || $json['success'] !== 1) {
                throw new ApiResponseException('Failed to retrieve search results.', $json['success'] ?? 0);
            }

            $total = $json['total_count'];

            $rows = (new NativeCrawler($json['results_html']))->filter('a.search_result_row');
            $returned = count($rows);

            foreach ($rows as $row) {
                yield StoreSearchParser::parse(new NativeCrawler($row));
            }

            $start += $returned;
        } while (++$page < self::MAX_PAGES && $returned === self::MAX_RESULTS && $start < $total);
    }

    public function getUrl(int $start = 0): string
    {
        return SteamProvider::buildStoreApiUrl(
            '/search/results/?' . http_build_query([
                'start' => $start,
                'count' => self::MAX_RESULTS,
                'filter' => self::FILTER,
                'infinite' => 1,
            ])
        );
    }
}
