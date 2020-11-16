<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Cookie;

use Amp\Http\Cookie\CookieAttributes;
use Amp\Http\Cookie\ResponseCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class StoreSessionCookie
{
    private const NAME = 'sessionid';

    private $cookie;

    public function __construct(ResponseCookie $cookie)
    {
        if ($cookie->getName() !== self::NAME) {
            throw new \InvalidArgumentException('Invalid cookie name.');
        }
        if ($cookie->getDomain() !== SteamProvider::STORE_DOMAIN) {
            throw new \InvalidArgumentException('Invalid cookie domain.');
        }

        $this->cookie = $cookie;
    }

    public static function create(string $value): self
    {
        return new self(
            new ResponseCookie(
                self::NAME,
                $value,
                CookieAttributes::default()->withDomain(SteamProvider::STORE_DOMAIN)->withSecure()
            )
        );
    }

    public function getCookie(): ResponseCookie
    {
        return $this->cookie;
    }
}
