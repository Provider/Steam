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
        // Reviews section.
        'appHubSubSection' => 10,
        'browsefilter' => 'mostrecent',
        'filterLanguage' => 'all',

        // Only page number matters; most other variables are ignored.
        'p' => null,

        /*
         * Number of results per page must be set, otherwise duplicate pages can occur more frequently.
         * The actual value has no impact on the number of results per page, which is always locked to 10.
         * Setting this value below 10 reduces the chance that page 2 is a duplicate of page 1.
         */
//        'numperpage' => 10,
//        'userreviewscursor' => '*',
//        'userreviewsoffset' => 0,
    ];

    /**
     * Starting page doesn't work: it reduces the total number of pages that will be downloaded but the list still
     * starts from the most recent review; reviews cannot be skipped over.
     *
     * @param int $appId
     * @param int $startingPage
     */
    public function __construct(int $appId, int $startingPage = 1)
    {
        $this->appId = $appId;
        $this->query['p'] = $startingPage;
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

                /*
                 * Stop condition is an empty body, which should only happen at the end of the list, but also
                 * happens randomly after about 4K pages.
                 */
                if ($response->getBody() === '') {
                    break;
                }

                ['reviews' => $reviews, 'form' => $form] = GameReviewsParser::parse(new Crawler($response->getBody()));

                $this->query = $form;

                foreach ($reviews as $review) {
                    yield $callable($review);
                }
            }
        });
    }

    public function getUrl(): string
    {
        return "https://steamcommunity.com/app/$this->appId/homecontent/?" . http_build_query($this->query);
    }
}
