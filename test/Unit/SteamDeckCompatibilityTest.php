<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use ScriptFUSION\Porter\Provider\Steam\Scrape\SteamDeckCompatibility;
use PHPUnit\Framework\TestCase;

/**
 * @see SteamDeckCompatibility
 */
final class SteamDeckCompatibilityTest extends TestCase
{
    /**
     * @dataProvider provideIds
     */
    public function testIdRoundTrip(int $id): void
    {
        self::assertInstanceOf(SteamDeckCompatibility::class, $compat = SteamDeckCompatibility::fromId($id));
        self::assertSame($id, $compat->toId());
    }

    public function provideIds(): iterable
    {
        yield from \iter\chunk(range(0, 3), 1);
    }
}
