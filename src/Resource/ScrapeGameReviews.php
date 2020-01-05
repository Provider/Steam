<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Iterator;
use Amp\Producer;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Scrape\GameReviewsParser;
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
        return new Producer(function (\Closure $callable) use ($connector): \Generator {
            $count = 0;

            while (true) {
                /** @var HttpResponse $response */
                $response = yield $connector->fetchAsync(new AsyncHttpDataSource($this->getUrl()));

                echo 'Req: ', ++$count, "\n";

                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException("Unexpected status code: {$response->getStatusCode()}.");
                }

                $json = json_decode($response->getBody(), true);

                // Stop condition is an empty recommendation list. This is quicker and easier than parsing HTML.
                if (!$json['recommendationids']) {
                    break;
                }

                $reviews = GameReviewsParser::parse(new Crawler($json['html']));

                foreach ($reviews as $review) {
                    yield $callable($review);
                }

                $this->query['cursor'] = $json['cursor'];
            }
        });
    }

    public function getUrl(): string
    {
        return "https://store.steampowered.com/appreviews/$this->appId?" . http_build_query($this->query);
    }
}
