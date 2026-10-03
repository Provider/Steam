<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\UserReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Fetches a single page of reviews for an app, plus the query summary (totals and score).
 *
 * For every review, streamed across all pages, use GetAllAppReviews instead.
 *
 * @see https://partner.steamgames.com/doc/webapi/IUserReviewsService#GetAppReviews
 */
final class GetAppReviews implements ProviderResource, Url
{
    /**
     * @param int $appId App to get reviews for.
     * @param AppReviewFilter $filter Sort order of the results.
     * @param string[]|string $languages API language codes to return reviews in. Defaults to every language.
     *     Pass a single code as a string for convenience.
     * @param ?int $dayRange Helpful filter only. Number of days back from today to look for reviews.
     *     Defaults to 30, max 365; 0 means no limit. Omitted when null.
     * @param AppReviewType $reviewType Only positive or only negative reviews, or all reviews.
     * @param AppReviewPurchaseType $purchaseType Only Steam purchases, only non-Steam purchases, or all reviews.
     * @param int $numPerPage Number of reviews to return. Defaults to 20, max 100.
     */
    public function __construct(
        private readonly int $appId,
        private readonly AppReviewFilter $filter = AppReviewFilter::Helpful,
        private readonly array|string $languages = ['all'],
        private readonly ?int $dayRange = null,
        private readonly AppReviewType $reviewType = AppReviewType::All,
        private readonly AppReviewPurchaseType $purchaseType = AppReviewPurchaseType::All,
        private readonly int $numPerPage = 20,
    ) {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
    {
        $response = \json_decode(
            (string)$connector->fetch(new HttpDataSource($this->getUrl())),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        $data = $response['response'] ?? null;
        $summary = \is_array($data) ? ($data['query_summary'] ?? null) : null;

        if (!\is_array($summary) || !\count($summary)) {
            throw new ApiResponseException('Failed to retrieve reviews.', 0);
        }

        return new UserReviewsRecords(
            new \ArrayIterator($data['reviews'] ?? []),
            $summary['total_positive'],
            $summary['total_negative'],
            $summary['review_score'],
            $summary['total_reviews'],
            $summary['review_score_desc'],
            $this
        );
    }

    public function getUrl(): string
    {
        $query = [
            'appid' => $this->appId,
            'filter' => $this->filter->value,
            'languages' => \is_string($this->languages) ? [$this->languages] : $this->languages,
            'review_type' => $this->reviewType->value,
            'purchase_type' => $this->purchaseType->value,
            'num_per_page' => $this->numPerPage,
        ];

        if ($this->dayRange !== null) {
            $query['day_range'] = $this->dayRange;
        }

        return SteamProvider::buildSteamworksApiUrl(
            '/IUserReviewsService/GetAppReviews/v1/?' . http_build_query($query)
        );
    }
}
