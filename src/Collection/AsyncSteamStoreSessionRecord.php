<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use Amp\Iterator;
use Amp\Promise;
use ScriptFUSION\Porter\Collection\AsyncProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;

class AsyncSteamStoreSessionRecord extends AsyncProviderRecords
{
    private $sessionCookie;

    public function __construct(Iterator $records, Promise $sessionCookie, AsyncResource $resource)
    {
        parent::__construct($records, $resource);

        $this->sessionCookie = $sessionCookie;
    }

    public function getSessionCookie(): Promise
    {
        return $this->sessionCookie;
    }
}
