<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Http\Cookie\ResponseCookie;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * Represents a logged-in user for store pages that require login.
 */
class StoreSession
{
    public function __construct(private SecureLoginCookie $secureLoginCookie)
    {
        $this->secureLoginCookie =
            // Ensure cookie has correct domain since it could have been created by CommunitySession.
            new SecureLoginCookie($secureLoginCookie->getCookie()->withDomain(SteamProvider::STORE_DOMAIN));
    }

    public static function create(Porter $porter, string $username, string $password): self
    {
        /** @var AsyncLoginRecord $steamLogin */
        $steamLogin = $porter->import(new Import(new SteamLogin($username, $password)))->findFirstCollection();

        return new self($steamLogin->getSecureLoginCookie()->await());
    }

    public function getSecureLoginCookie(): ResponseCookie
    {
        return $this->secureLoginCookie->getCookie();
    }
}
