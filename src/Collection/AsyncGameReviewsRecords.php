<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use Amp\Future;
use ScriptFUSION\Porter\Collection\AsyncProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;

class AsyncGameReviewsRecords extends AsyncProviderRecords
{
    public function __construct(\Iterator $records, private readonly Future $totalReviews, AsyncResource $resource)
    {
        parent::__construct($records, $resource);
    }

    /**
     * Gets the total number of reviews.
     *
     * @return Future<int> Number of reviews.
     */
    public function getTotal(): Future
    {
        return $this->totalReviews;
    }
}
