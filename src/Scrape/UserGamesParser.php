<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Symfony\Component\DomCrawler\Crawler;

final class UserGamesParser
{
    public static function parse(Crawler $crawler): iterable
    {
        $json = $crawler->filter('#gameslist_config')->attr('data-profile-gameslist');
        $data = json_decode($json, true, JSON_THROW_ON_ERROR, JSON_THROW_ON_ERROR);

        if (!isset($data['rgGames'])) {
            throw new ParserException('Invalid games list.');
        }

        yield from $data['rgGames'];
    }
}
