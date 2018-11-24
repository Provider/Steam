<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\Cookie\Cookie;
use Amp\Artax\Cookie\CookieJar;
use Amp\Promise;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncSteamStoreSessionRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Cookie\StoreSessionCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\CreateSteamStoreSession;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLogin;
use ScriptFUSION\Porter\Specification\AsyncImportSpecification;

final class CuratorSession
{
    private $secureLoginCookie;

    private $storeSessionCookie;

    public function __construct(SecureLoginCookie $secureLoginCookie, StoreSessionCookie $storeSessionCookie)
    {
        $this->secureLoginCookie = $secureLoginCookie;
        $this->storeSessionCookie = $storeSessionCookie;
    }

    public static function create(Porter $porter, string $username, string $password): Promise
    {
        return \Amp\call(static function () use ($porter, $username, $password): \Generator {
            /** @var AsyncLoginRecord $steamLogin */
            $steamLogin = $porter->importAsync(new AsyncImportSpecification(
                new SteamLogin($username, $password)
            ))->findFirstCollection();

            $secureLoginCookie = yield $steamLogin->getSecureLoginCookie();

            return yield self::createFromCookie($secureLoginCookie, $porter);
        });
    }

    /**
     * Create session from existing login cookie. This can be an effective way to avoid login captcha.
     * However, the session will eventually expire.
     */
    public static function createFromCookie(SecureLoginCookie $secureLoginCookie, Porter $porter): Promise
    {
        return \Amp\call(static function () use ($secureLoginCookie, $porter): \Generator {
            /** @var AsyncSteamStoreSessionRecord $storeSession */
            $storeSession = $porter->importAsync(new AsyncImportSpecification(
                new CreateSteamStoreSession
            ))->findFirstCollection();

            $storeSessionCookie = yield $storeSession->getSessionCookie();

            return new self($secureLoginCookie, $storeSessionCookie);
        });
    }

    public function apply(CookieJar $cookieJar): void
    {
        $cookieJar->store($this->getSecureLoginCookie());
        $cookieJar->store($this->getStoreSessionCookie());
    }

    public function getSecureLoginCookie(): Cookie
    {
        return $this->secureLoginCookie->getCookie();
    }

    public function getStoreSessionCookie(): Cookie
    {
        return $this->storeSessionCookie->getCookie();
    }
}
