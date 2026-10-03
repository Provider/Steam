<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

/**
 * Which reviews the GetAppReviews API returns based on purchase history.
 *
 * @see https://partner.steamgames.com/doc/webapi/IUserReviewsService#EUserReviewsPurchaseType
 */
enum AppReviewPurchaseType: int
{
    case Steam = 0;
    case All = 1;
    case NonSteamPurchase = 2;
}
