<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Options\EncapsulatedOptions;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\UserReviewsRecords;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * @see https://partner.steamgames.com/doc/store/getreviews
 */
final class GetUserReviewsList implements ProviderResource, Url
{
    private $appId;

    public function __construct(int $appId)
    {
        $this->appId = $appId;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector, EncapsulatedOptions $options = null)
    {
        $response = \json_decode((string)$connector->fetch($this->getUrl()), true);

        if ($response['success'] !== 1) {
            throw new ApiResponseException('Failed to retrieve reviews.', $response['success']);
        }

        $summary = $response['query_summary'];

        return new UserReviewsRecords(
            new \ArrayIterator($response['reviews']),
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
        return SteamProvider::buildStoreApiUrl("/appreviews/$this->appId?json=1&language=all&purchase_type=all");
    }
}
