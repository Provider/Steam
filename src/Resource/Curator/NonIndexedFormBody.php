<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

use Amp\Artax\RequestBody;
use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Success;

class NonIndexedFormBody implements RequestBody
{
    private $fields = [];

    public function addFields(array $fields): void
    {
        $this->fields = $fields;
    }

    public function getHeaders(): Promise
    {
        return new Success(['Content-Type' => 'application/x-www-form-urlencoded']);
    }

    public function createBodyStream(): InputStream
    {
        return new InMemoryStream($this->encode());
    }

    public function getBodyLength(): Promise
    {
        return new Success(strlen($this->encode()));
    }

    protected function encode(): string
    {
        return preg_replace('[%5B\d+%5D=]', '%5B%5D=', http_build_query($this->fields));
    }
}
