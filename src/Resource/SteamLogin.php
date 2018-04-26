<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Artax\FormBody;
use Amp\Deferred;
use Amp\Iterator;
use Amp\Producer;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * TODO: 2FA support.
 */
class SteamLogin implements AsyncResource
{
    private $username;

    private $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetchAsync(ImportConnector $connector): Iterator
    {
        $loginCookie = new Deferred;

        return new AsyncLoginRecord(
            new Producer(function (\Closure $emit) use ($connector, $loginCookie) {
                $baseConnector = $connector->findBaseConnector();
                if (!$baseConnector instanceof AsyncHttpConnector) {
                    throw new \InvalidArgumentException('Unexpected connector type.');
                }

                $options = $baseConnector->getOptions()
                    ->setMethod('POST')
                    ->setBody($body = new FormBody)
                ;

                $body->addField('username', $this->username);

                $json = json_decode(
                    (string)yield $connector->fetchAsync(SteamProvider::buildStoreApiUrl('/login/getrsakey/')),
                    true
                );

                if (!$json['success'] ?? false) {
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
                    (string)yield $connector->fetchAsync(SteamProvider::buildStoreApiUrl('/login/dologin/')),
                    true
                );

                if (!$json['success'] ?? false) {
                    throw new SteamLoginException('Unable to log in using supplied credentials.');
                }

                $loginCookie->resolve(
                    $options->getCookieJar()->get(SteamProvider::STORE_DOMAIN, '', 'steamLoginSecure')[0]
                );

                $emit($json);
            }),
            $loginCookie->promise(),
            $this
        );
    }
}
