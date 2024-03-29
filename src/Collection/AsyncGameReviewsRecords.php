<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use Amp\Future;
use ScriptFUSION\Porter\Collection\ProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;

class AsyncGameReviewsRecords extends ProviderRecords
{
    public function __construct(\Iterator $records, private readonly Future $totalReviews, ProviderResource $resource)
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
