<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\NonIndexedFormBody;
use PHPUnit\Framework\TestCase;

/**
 * @see NonIndexedFormBody
 */
final class NonIndexedFormBodyTest extends TestCase
{
    /**
     * Tests that indexed parameters (numeric array indexes) are encoded without numeral indexes.
     */
    public function testEncodeIndexedParams(): void
    {
        $body = new NonIndexedFormBody;
        $body->addFields([
            'foo' => 'bar',
            'baz' => ['qux', 'quux'],
            'corge' => ['grault' => 'garply']
        ]);

        $encoded = \Amp\Promise\wait($body->createBodyStream()->read());

        self::assertSame('foo=bar&baz%5B%5D=qux&baz%5B%5D=quux&corge%5Bgrault%5D=garply', $encoded);
    }
}
