<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Scrape\NativeCrawler;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @see NativeCrawler
 */
final class NativeCrawlerTest extends TestCase
{
    /**
     * Tests that NativeCrawler does not user an HTML 5 parser (but rather, the native one).
     */
    public function testCrawlerIsNative(): void
    {
        $nativeCrawler = new NativeCrawler();
        $prop = new \ReflectionProperty(Crawler::class, 'html5Parser');

        self::assertNull($prop->getValue($nativeCrawler));
    }
}
