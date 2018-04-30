<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Cookie;

use Amp\Artax\Cookie\Cookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

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

    public static function create(string $value): self
    {
        return new self(
            new Cookie(
                'steamLoginSecure',
                $value,
                null,
                null,
                SteamProvider::STORE_DOMAIN,
                true
            )
        );
    }

    public function getCookie(): Cookie
    {
        return $this->cookie;
    }
}
