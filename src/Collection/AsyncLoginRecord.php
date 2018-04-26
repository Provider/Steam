<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Collection;

use Amp\Iterator;
use Amp\Promise;
use ScriptFUSION\Porter\Collection\AsyncProviderRecords;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;

class AsyncLoginRecord extends AsyncProviderRecords
{
    private $secureLoginCookie;

    public function __construct(Iterator $records, Promise $secureLoginCookie, AsyncResource $resource)
    {
        parent::__construct($records, $resource);

        $this->secureLoginCookie = $secureLoginCookie;
    }

    public function getSecureLoginCookie(): Promise
    {
        return $this->secureLoginCookie;
    }
}
