<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Cookie;

use Amp\Artax\Cookie\Cookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class StoreSessionCookie
{
    private $cookie;

    public function __construct(Cookie $cookie)
    {
        if ($cookie->getName() !== 'sessionid') {
            throw new \InvalidArgumentException('Invalid cookie name.');
        }
        if ($cookie->getDomain() !== SteamProvider::STORE_DOMAIN) {
            throw new \InvalidArgumentException('Invalid cookie domain.');
        }

        $this->cookie = $cookie;
    }

    public function getCookie(): Cookie
    {
        return $this->cookie;
    }
}
