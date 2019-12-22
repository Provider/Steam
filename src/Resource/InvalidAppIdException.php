<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

/**
 * The exception that is thrown when an app ID is determined to be invalid because its store page is redirecting.
 */
final class InvalidAppIdException extends \RuntimeException
{
    // Intentionally empty.
}
