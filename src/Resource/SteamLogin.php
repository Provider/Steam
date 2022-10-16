<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\DeferredFuture;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Cookie\ResponseCookie;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * TODO: 2FA support.
 */
final class SteamLogin implements AsyncResource
{
    public function __construct(private readonly string $username, private readonly string $password)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): \Iterator
    {
        $loginCookie = new DeferredFuture();

        return new AsyncLoginRecord(
            (function () use ($connector, $loginCookie): \Generator {
                try {
                    [$json, $cookie] = $this->login($connector);
                } catch (\Throwable $throwable) {
                    $loginCookie->error($throwable);

                    throw $throwable;
                }

                $loginCookie->complete($cookie);

                yield $json;
            })(),
            $loginCookie->getFuture(),
            $this
        );
    }

    private function login(ImportConnector $connector): array
    {
        $baseConnector = $connector->findBaseConnector();
        if (!$baseConnector instanceof AsyncHttpConnector) {
            throw new \InvalidArgumentException('Unexpected connector type.');
        }

        $source = (new AsyncHttpDataSource(SteamProvider::buildStoreApiUrl('/login/getrsakey/')))
            ->setMethod('POST')
            ->setBody($body = new FormBody)
        ;
        $body->addField('username', $this->username);
        $body->addField('donotcache', (string)((int)microtime(true) * 1000));

        $json = json_decode(
            (string)$connector->fetchAsync($source),
            true
        );

        if (!($json['success'] ?? false)) {
            throw new SteamLoginException('Unable to fetch RSA key.');
        }

        $rsa = new RSA;
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $rsa->loadKey([
            'n' => new BigInteger($json['publickey_mod'], 16),
            'e' => new BigInteger($json['publickey_exp'], 16),
        ]);

        $body->addFields([
            'password' => base64_encode($rsa->encrypt($this->password)),
            'rsatimestamp' => $json['timestamp'],
        ]);

        $json = json_decode(
            (string)$response = $connector->fetchAsync(
                (new AsyncHttpDataSource(SteamProvider::buildStoreApiUrl('/login/dologin/')))
                    ->setMethod('POST')
                    ->setBody($body)
            ),
            true
        );

        if (!($json['success'] ?? false)) {
            $message = $json['message'] ?? '';
            throw new SteamLoginException("Unable to log in using supplied credentials.\n$message");
        }

        $steamLoginCookie = current(array_filter(
            $baseConnector->getCookieJar()->getAll(),
            static function (ResponseCookie $cookie) {
                return $cookie->getName() === 'steamLoginSecure';
            }
        ));

        assert($steamLoginCookie);

        return [$json, new SecureLoginCookie($steamLoginCookie)];
    }
}
