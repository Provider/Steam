<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

final class AppDetailsParser
{
    use StaticClass;

    public static function parseStorePage(string $html): array
    {
        $crawler = new Crawler($html);

        $bodyClasses = explode(' ', $crawler->filter('body')->attr('class'));
        if (!in_array('v6', $bodyClasses, true)) {
            throw new ParserException('Unexpected version! Expected: v6.');
        }

        $name = $crawler->filter('.apphub_AppName')->text();
        $type = mb_strtolower(
            preg_replace(
                '[.*Is this (\S+)\b.*]',
                '$1',
                $crawler->filter('.responsive_apppage_details_right.heading')->text()
            )
        );

        $date = $crawler->filter('.release_date > .date');
        $release_date = $date->count() ? new \DateTimeImmutable($date->text()) : null;

        return compact('name', 'type', 'release_date');
    }
}
