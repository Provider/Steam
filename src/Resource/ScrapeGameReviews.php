<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Deferred;
use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncGameReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\Scrape\GameReviewsParser;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use Symfony\Component\DomCrawler\Crawler;

final class ScrapeGameReviews implements AsyncResource, Url
{
    private $appId;

    private $query = [
        'filter' => 'recent', // Order by date.
        'purchase_type' => 'all', // Steam and non-Steam.
        'language' => 'all',
        'review_type' => 'all', // Positive and negative.
        'filter_offtopic_activity' => 0,
    ];

    public function __construct(int $appId)
    {
        $this->appId = $appId;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        $total = new Deferred();

        return new AsyncGameReviewsRecords(
            new Producer(function (\Closure $callable) use ($connector, $total): \Generator {
                while (true) {
                    /** @var HttpResponse $response */
                    $response = yield $connector->fetchAsync(new AsyncHttpDataSource($this->getUrl()));

                    if ($response->getStatusCode() !== 200) {
                        throw new \RuntimeException("Unexpected status code: {$response->getStatusCode()}.");
                    }

                    $json = json_decode($response->getBody(), true);

                    // Stop condition is an empty recommendation list. This is quicker and easier than parsing HTML.
                    if (!$json['recommendationids']) {
                        break;
                    }

                    if (isset($json['review_score'])) {
                        $total->resolve($this->parseResultsTotal($json['review_score']));
                    }

                    $reviews = GameReviewsParser::parse(new Crawler($json['html']));

                    foreach ($reviews as $review) {
                        yield $callable($review);
                    }

                    $this->query['cursor'] = $json['cursor'];
                }
            }),
            $total->promise(),
            $this
        );
    }

    public function getUrl(): string
    {
        return "https://store.steampowered.com/appreviews/$this->appId?" . http_build_query($this->query);
    }

    private function parseResultsTotal(string $reviewScore): int
    {
        if (preg_match('[<b>(\\d+)</b>]', $reviewScore, $matches)) {
            return (int)$matches[1];
        }

        throw new ParserException('Failed to parse results total.');
    }
}
