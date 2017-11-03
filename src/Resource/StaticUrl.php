<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource;

interface StaticUrl
{
    public static function getUrl(): string;
}
