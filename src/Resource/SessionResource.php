<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Http\Client\Cookie\CookieJar;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

abstract class SessionResource
{
    /** @var CuratorSession */
    protected $session;

    public function setSession(CuratorSession $curatorSession): void
    {
        $this->session = $curatorSession;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    protected function applySessionCookies(CookieJar $cookieJar): void
    {
        if ($this->session) {
            $cookieJar->store($this->session->getSecureLoginCookie());
            $cookieJar->store($this->session->getStoreSessionCookie());
        }
    }
}
