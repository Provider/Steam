<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use Amp\Future;
use ScriptFUSION\Porter\Collection\ProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Cookie\StoreSessionCookie;

class AsyncSteamStoreSessionRecord extends ProviderRecords
{
    public function __construct(\Iterator $records, private readonly Future $sessionCookie, ProviderResource $resource)
    {
        parent::__construct($records, $resource);
    }

    /**
     * @return Future<StoreSessionCookie>
     */
    public function getSessionCookie(): Future
    {
        return $this->sessionCookie;
    }
}
