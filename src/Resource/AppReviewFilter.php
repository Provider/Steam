<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

/**
 * Sort order for GetAppReviews API results.
 *
 * @see https://partner.steamgames.com/doc/webapi/IUserReviewsService#EUserReviewsAppReviewsFilter
 */
enum AppReviewFilter: int
{
    case Helpful = 0;
    case Recent = 1;
    case Updated = 2;
    case Funny = 3;
}
