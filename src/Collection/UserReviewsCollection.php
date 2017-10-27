<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use ScriptFUSION\Porter\Collection\CountableProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;

final class UserReviewsCollection extends CountableProviderRecords
{
    private $totalPositive;

    private $totalNegative;

    private $reviewScore;

    private $reviewScoreDescription;

    public function __construct(
        \Iterator $providerRecords,
        int $totalPositive,
        int $totalNegative,
        int $reviewScore,
        int $count,
        string $reviewScoreDescription,
        ProviderResource $resource
    ) {
        parent::__construct($providerRecords, $count, $resource);

        $this->totalPositive = $totalPositive;
        $this->totalNegative = $totalNegative;
        $this->reviewScore = $reviewScore;
        $this->reviewScoreDescription = $reviewScoreDescription;
    }

    public function getTotalPositive(): int
    {
        return $this->totalPositive;
    }

    public function getTotalNegative(): int
    {
        return $this->totalNegative;
    }

    public function getReviewScore(): int
    {
        return $this->reviewScore;
    }

    public function getReviewScoreDescription(): string
    {
        return $this->reviewScoreDescription;
    }
}
