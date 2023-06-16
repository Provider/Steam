<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Amp\DeferredFuture;
use Amp\Http\Client\Form;
use phpseclib\Crypt\RSA;
use phpseclib\Math\BigInteger;
use ScriptFUSION\Porter\Connector\ImportConnector;
use ScriptFUSION\Porter\Net\Http\HttpConnector;
use ScriptFUSION\Porter\Net\Http\HttpDataSource;
use ScriptFUSION\Porter\Provider\Resource\ProviderResource;
use ScriptFUSION\Porter\Provider\Steam\Collection\AsyncLoginRecord;
use ScriptFUSION\Porter\Provider\Steam\Cookie\SecureLoginCookie;
use ScriptFUSION\Porter\Provider\Steam\SteamProvider;

/**
 * TODO: 2FA support.
 */
final class SteamLogin implements ProviderResource
{
    public function __construct(private readonly string $username, private readonly string $password)
    {
    }

    public function getProviderClassName(): string
    {
        return SteamProvider::class;
    }

    public function fetch(ImportConnector $connector): \Iterator
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
        if (!$baseConnector instanceof HttpConnector) {
            throw new \InvalidArgumentException('Unexpected connector type.');
        }

        $source = new HttpDataSource(SteamProvider::buildSteamworksApiUrl(
            '/IAuthenticationService/GetPasswordRSAPublicKey/v1/?account_name=' . urlencode($this->username)
        ));

        $json = json_decode(
            (string)$response = $connector->fetch($source),
            true
        );

        if (!isset($json['response']['publickey_mod'])) {
            throw new SteamLoginException('Unable to fetch RSA key.');
        }

        $rsa = new RSA;
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $rsa->loadKey([
            'n' => new BigInteger($json['response']['publickey_mod'], 16),
            'e' => new BigInteger($json['response']['publickey_exp'], 16),
        ]);

        $body = new Form();
        $body->addField('account_name', $this->username);
        $body->addField('encrypted_password', base64_encode($rsa->encrypt($this->password)));
        $body->addField('encryption_timestamp', $json['response']['timestamp']);

        $json = json_decode(
            (string)$response = $connector->fetch(
                (new HttpDataSource(SteamProvider::buildSteamworksApiUrl(
                    '/IAuthenticationService/BeginAuthSessionViaCredentials/v1/'
                )))
                    ->setMethod('POST')
                    ->setBody($body)
            ),
            true
        );

        if (!isset($json['response']['client_id'])) {
            throw new SteamLoginException("Unable to log in using supplied credentials.");
        }
        $sessionParams = $json['response'];

        $body = new Form();
        foreach (['client_id', 'request_id'] as $field) {
            $body->addField($field, $sessionParams[$field]);
        }

        $json = json_decode(
            (string)$response = $connector->fetch(
                (new HttpDataSource(SteamProvider::buildSteamworksApiUrl(
                    '/IAuthenticationService/PollAuthSessionStatus/v1/'
                )))
                    ->setMethod('POST')
                    ->setBody($body)
            ),
            true,
        );

        return [$json, SecureLoginCookie::create("$sessionParams[steamid]||{$json['response']['access_token']}")];
    }
}
