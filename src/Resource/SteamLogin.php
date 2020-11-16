<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\Deferred;
use Amp\Http\Client\Body\FormBody;
use Amp\Http\Cookie\RequestCookie;
use Amp\Iterator;
use Amp\Producer;
use Amp\Promise;
use League\Uri\Http;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use Psr\Http\Message\UriInterface;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpConnector;
use ScriptFUSION\Porter\Net\Http\AsyncHttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\AsyncResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;
use function Amp\call;

/**
 * TODO: 2FA support.
 */
final class SteamLogin implements AsyncResource
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
            new Producer(function (\Closure $emit) use ($connector, $loginCookie): \Generator {
                try {
                    [$json, $cookie] = yield $this->login($connector);
                } catch (\Throwable $throwable) {
                    $loginCookie->fail($throwable);

                    throw $throwable;
                }

                $loginCookie->resolve($cookie);

                yield $emit($json);
            }),
            $loginCookie->promise(),
            $this
        );
    }

    private function login(ImportConnector $connector): Promise
    {
        return call(function () use ($connector): \Generator {
            $baseConnector = $connector->findBaseConnector();
            if (!$baseConnector instanceof AsyncHttpConnector) {
                throw new \InvalidArgumentException('Unexpected connector type.');
            }

            $source = (new AsyncHttpDataSource(SteamProvider::buildStoreApiUrl('/login/getrsakey/')))
                ->setMethod('POST')
                ->setBody($body = new FormBody)
            ;
            $body->addField('username', $this->username);
            $body->addField('donotcache', (string)(microtime(true) * 1000 | 0));

            $json = json_decode(
                (string)yield $connector->fetchAsync($source),
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
                (string)$response = yield $connector->fetchAsync(
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
                yield $baseConnector->getCookieJar()->get(Http::createFromString(SteamProvider::STORE_API_URL)),
                static function (RequestCookie $cookie) {
                    return $cookie->getName() === 'steamLoginSecure';
                }
            ));

            assert($steamLoginCookie);

            return [$json, SecureLoginCookie::create($steamLoginCookie->getValue())];
        });
    }
}
