<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\Porter\Connector\Recoverable\RecoverableException;

class InvalidMarkupException extends ParserException implements RecoverableException
{
    // Intentionally empty.
}
