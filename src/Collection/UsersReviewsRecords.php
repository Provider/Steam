<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;
use ScriptFUSION\Porter\Collection\CountableProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;

/**
 * Represents a collection of a specific user's reviews.
 */
class UsersReviewsRecords extends CountableProviderRecords
{
    private $avatarUrl;

    public function __construct(\Iterator $providerRecords, int $count, string $avatarUrl, ProviderResource $resource)
    {
        parent::__construct($providerRecords, $count, $resource);

        $this->avatarUrl = $avatarUrl;
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }
}
