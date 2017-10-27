<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

use Throwable;

/**
 * The exception that is thrown when the Steam API responds with a failure code.
 */
final class ApiResponseException extends \RuntimeException
{
    public function __construct(string $message, int $code, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
