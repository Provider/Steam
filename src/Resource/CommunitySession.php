<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Http\Cookie\ResponseCookie;
use ScriptFUSION\Porter\Import\Import;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

final class CommunitySession
{
    public function __construct(private SecureLoginCookie $secureLoginCookie)
    {
        $this->secureLoginCookie =
            // Ensure cookie has correct domain since it could have been created by CuratorSession.
            new SecureLoginCookie($this->secureLoginCookie->getCookie()->withDomain(SteamProvider::COMMUNITY_DOMAIN));
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
