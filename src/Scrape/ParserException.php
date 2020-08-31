<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

/**
 * The exception that is thrown when the parser encounters an error.
 */
class ParserException extends \RuntimeException
{
    public const UNEXPECTED_VERSION = 1;
    public const UNEXPECTED_TYPE = 2;
    public const MISSING_CANONICAL_APP_ID = 3;
}
