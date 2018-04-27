<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Cookie;

use Amp\Artax\Cookie\Cookie;

final class SecureLoginCookie
{
    private $cookie;

    public function __construct(Cookie $cookie)
    {
        if ($cookie->getName() !== 'steamLoginSecure') {
            throw new \InvalidArgumentException('Invalid cookie name.');
        }

        $this->cookie = $cookie;
    }

    public function getCookie(): Cookie
    {
        return $this->cookie;
    }
}
