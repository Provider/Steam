<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Http\Cookie\ResponseCookie;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncSteamStoreSessionRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\Cookie\StoreSessionCookie;
use ScriptFUSION\Porter\Provider\Steam\Resource\CreateSteamStoreSession;
use ScriptFUSION\Porter\Provider\Steam\Resource\SteamLogin;
use ScriptFUSION\Porter\Provider\Steam\Resource\StoreSession;

final class CuratorSession extends StoreSession
{
    public function __construct(
        SecureLoginCookie $secureLoginCookie,
        private readonly StoreSessionCookie $storeSessionCookie,
    ) {
        parent::__construct($secureLoginCookie);
    }

    public static function create(Porter $porter, string $username, string $password): self
    {
        /** @var AsyncLoginRecord $steamLogin */
        $steamLogin = $porter->import(new Import(new SteamLogin($username, $password)))->findFirstCollection();

        $secureLoginCookie = $steamLogin->getSecureLoginCookie()->await();

        return self::createFromCookie($secureLoginCookie, $porter);
    }

    /**
     * Create session from existing login cookie. This can be an effective way to avoid login captcha.
     * However, the session will eventually expire.
     */
    public static function createFromCookie(SecureLoginCookie $secureLoginCookie, Porter $porter): self
    {
        /** @var AsyncSteamStoreSessionRecord $storeSession */
        $storeSession = $porter->import(new Import(new CreateSteamStoreSession))->findFirstCollection();

        $storeSessionCookie = $storeSession->getSessionCookie()->await();

        return new self($secureLoginCookie, $storeSessionCookie);
    }

    public function getStoreSessionCookie(): ResponseCookie
    {
        return $this->storeSessionCookie->getCookie();
    }
}
