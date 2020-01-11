<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use Amp\Iterator;
use Amp\Promise;
use ScriptFUSION\Porter\Collection\AsyncProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;

class AsyncGameReviewsRecords extends AsyncProviderRecords
{
    private $totalReviews;

    public function __construct(Iterator $records, Promise $totalReviews, AsyncResource $resource)
    {
        parent::__construct($records, $resource);

        $this->totalReviews = $totalReviews;
    }

    /**
     * Gets the total number of reviews.
     *
     * @return Promise<int> Number of reviews.
     */
    public function getTotal(): Promise
    {
        return $this->totalReviews;
    }
}
