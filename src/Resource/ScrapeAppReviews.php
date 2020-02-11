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

final class ScrapeAppReviews implements AsyncResource, Url
{
    private $appId;

    private $query = [
        'filter' => 'recent', // Order by date.
        'purchase_type' => 'all', // Steam and non-Steam.
        'language' => 'all',
        'review_type' => 'all', // Positive and negative.
        'filter_offtopic_activity' => 0,
        'start_date' => -1,
        'end_date' => -1,
        // Must be set to 'include' otherwise start/end date are ignored.
        'date_range_type' => 'include',
    ];

    /** @var int */
    private $total;

    /** @var int */
    private $count = 0;

    public function __construct(int $appId, \DateTimeImmutable $startDate = null, \DateTimeImmutable $endDate = null)
    {
        $this->appId = $appId;
        $startDate && $this->query['start_date'] = $startDate->getTimestamp();
        $endDate && $this->query['end_date'] = $endDate->getTimestamp();
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        $deferredTotal = new Deferred();
        $resolved = false;

        return new AsyncGameReviewsRecords(
            new Producer(function (\Closure $callable) use ($connector, $deferredTotal, $resolved): \Generator {
                $try = 1;

                do {
                    try {
                        /** @var HttpResponse $response */
                        $response = yield $connector->fetchAsync(new AsyncHttpDataSource($this->getUrl()));

                        if ($response->getStatusCode() !== 200) {
                            throw new \RuntimeException("Unexpected status code: {$response->getStatusCode()}.");
                        }

                        $json = json_decode($response->getBody(), true);

                        if (!isset($json['success']) || $json['success'] !== 1) {
                            throw new InvalidAppIdException("Application ID \"$this->appId\" is invalid.");
                        }

                        if (isset($json['review_score'])) {
                            $deferredTotal->resolve($this->total = $this->parseResultsTotal($json['review_score']));
                            $resolved = true;
                        }
                    } catch (\Throwable $throwable) {
                        if (!$resolved) {
                            $deferredTotal->fail($throwable);
                        }

                        throw $throwable;
                    }

                    if ($json['recommendationids']) {
                        $reviews = GameReviewsParser::parse(new Crawler($json['html']));

                        foreach ($reviews as $review) {
                            ++$this->count;

                            yield $callable($review);
                        }
                    }

                    if (!$json['recommendationids'] && $this->count < $this->total - 2) {
                        if (++$try <= 5) {
                            /*
                             * Steam frequently misreports a cursor as finished when it's not. Happens more often
                             * during peak times, and chronically so during sales. However, sales create deeper
                             * problems, such as under-reporting the total, which this does nothing to combat.
                             */
                            continue;
                        }

                        throw new TotalLessThanExpectedException("Expected: $this->total, got: $this->count.");
                    }

                    // Advance cursor.
                    $this->query['cursor'] = $json['cursor'];

                    // Stop condition is an empty recommendation list. This is quicker and easier than parsing HTML.
                } while ($json['recommendationids']);
            }),
            $deferredTotal->promise(),
            $this
        );
    }

    public function getUrl(): string
    {
        return "https://store.steampowered.com/appreviews/$this->appId?" . http_build_query($this->query);
    }

    private function parseResultsTotal(string $reviewScore): int
    {
        if (preg_match('[<b>([\\d,]+)</b>]', $reviewScore, $matches)) {
            return (int)strtr($matches[1], [',' => '']);
        }

        throw new ParserException('Failed to parse results total.');
    }
}
