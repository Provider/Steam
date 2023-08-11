<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Symfony\Component\DomCrawler\Crawler;

final class UserGamesParser
{
    public static function parse(Crawler $crawler): iterable
    {
        $config = $crawler->filter('#gameslist_config');

        if (!count($config)) {
            throw new ParserException(
                'Unexpected page content. This usually indicates a private profile.',
                ParserException::UNEXPECTED_CONTENT,
            );
        }

        $json = $config->attr('data-profile-gameslist');
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (!isset($data['rgGames'])) {
            throw new ParserException('Invalid games list.', ParserException::INVALID_GAMES_LIST);
        }

        if (!count($data['rgGames'])) {
            throw new ParserException(
                'Empty games list. This usually indicates games are private.',
                ParserException::EMPTY_GAMES_LIST,
            );
        }

        yield from $data['rgGames'];
    }
}
