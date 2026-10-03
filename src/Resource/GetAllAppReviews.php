<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\DeferredFuture;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Net\Http\HttpResponse;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncGameReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ReviewSource;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Streams every review for an app, following cursor pagination to the last page.
 *
 * For a single page of reviews plus the query summary, use GetAppReviews instead.
 *
 * @see https://partner.steamgames.com/doc/webapi/IUserReviewsService#GetAppReviews
 */
final class GetAllAppReviews implements ProviderResource, Url
{
    private int $appId;

    private array $query = [
        // k_EUserReviewsAppReviewsFilter_Recent. Order by date so cursor pagination eventually ends.
        'filter' => 1,
        // k_EUserReviewsPurchaseType_All. Steam and non-Steam.
        'purchase_type' => 1,
        'languages' => ['all'],
        // k_EUserReviewsReviewType_All. Positive and negative.
        'review_type' => 0,
        'filter_offtopic_activity' => false,
    ];

    private const STEAMID_BASE = 76561197960265728;

    private int $total;

    private int $count = 0;

    public function __construct(int $appId, ?\DateTimeImmutable $startDate = null, ?\DateTimeImmutable $endDate = null)
    {
        $this->appId = $appId;

        // New API ignores date range unless both bounds are set.
        if ($startDate && $endDate) {
            $this->query['date_range_start'] = $startDate->getTimestamp();
            $this->query['date_range_end'] = $endDate->getTimestamp();
        } elseif ($startDate) {
            $this->query['date_range_start'] = $startDate->getTimestamp();
            $this->query['date_range_end'] = time();
        } elseif ($endDate) {
            $this->query['date_range_start'] = 0;
            $this->query['date_range_end'] = $endDate->getTimestamp();
        }
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $deferredTotal = new DeferredFuture();
        $deferredTotal->getFuture()->ignore();
        $resolved = false;

        return new AsyncGameReviewsRecords(
            (function () use ($connector, $deferredTotal, $resolved): \Generator {
                $try = 1;

                do {
                    try {
                        /** @var HttpResponse $response */
                        $response = $connector->fetch(new HttpDataSource($this->getUrl()));

                        if ($response->getStatusCode() !== 200) {
                            throw new \RuntimeException("Unexpected status code: {$response->getStatusCode()}.");
                        }

                        $json = json_decode($response->getBody(), true, flags: JSON_THROW_ON_ERROR);

                        $data = $json['response'] ?? null;

                        if (!is_array($data) || $data === []) {
                            throw new InvalidAppIdException("Application ID \"$this->appId\" is invalid.");
                        }

                        if (!$resolved) {
                            $this->total = $data['total_matching']
                                ?? $data['query_summary']['total_reviews']
                                ?? 0;

                            $deferredTotal->complete($this->total);
                            $resolved = true;
                        }
                    } catch (\Throwable $throwable) {
                        if (!$resolved) {
                            $deferredTotal->error($throwable);
                        }

                        throw $throwable;
                    }

                    $reviews = $data['reviews'] ?? [];

                    if (count($reviews)) {
                        foreach ($reviews as $review) {
                            ++$this->count;

                            yield self::mapReview($review);
                        }
                    }

                    if (!count($reviews) && $this->count < $this->total - 2) {
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
                    if (isset($data['cursor'])) {
                        $this->query['cursor'] = $data['cursor'];
                    }

                    // Stop condition is an empty reviews list.
                } while (count($reviews));
            })(),
            $deferredTotal->getFuture(),
            $this
        );
    }

    public function getUrl(): string
    {
        return SteamProvider::buildSteamworksApiUrl(
            '/IUserReviewsService/GetAppReviews/v1/?' . http_build_query(
                ['appid' => $this->appId] + $this->query
            )
        );
    }

    /**
     * Maps a GetAppReviews API review to the legacy scraped shape, preserving all API fields.
     */
    private static function mapReview(array $review): array
    {
        $steamid = $review['author']['steamid'];

        // Preserve legacy 32-bit account ID for BC while keeping the full 64-bit SteamID in the raw fields.
        $userId = (int)$steamid - self::STEAMID_BASE;

        return [
            'review_id' => (int)$review['recommendationid'],
            'user_id' => $userId,
            'positive' => $review['voted_up'],
            'date' => (new \DateTimeImmutable)->setTimestamp($review['timestamp_created']),
            'source' => $review['steam_purchase'] ? ReviewSource::STEAM : ReviewSource::STEAM_KEY,
            'review_playtime' => $review['author']['playtime_at_review'] ?? null,
        ] + $review;
    }
}
