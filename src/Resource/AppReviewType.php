<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

/**
 * Which reviews the GetAppReviews API returns based on recommendation.
 *
 * @see https://partner.steamgames.com/doc/webapi/IUserReviewsService#EUserReviewsReviewType
 */
enum AppReviewType: int
{
    case All = 0;
    case Positive = 1;
    case Negative = 2;
}
