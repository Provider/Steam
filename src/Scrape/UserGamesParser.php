<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Symfony\Component\DomCrawler\Crawler;

final class UserGamesParser
{
    public static function parse(Crawler $crawler): iterable
    {
        $scripts = $crawler
            ->filter('script')
            ->reduce(fn (Crawler $node) => str_starts_with($node->text(), 'window.SSR='))
        ;

        if (count($scripts) !== 1) {
            throw new ParserException(
                'Unexpected page content.',
                ParserException::UNEXPECTED_CONTENT,
            );
        }

        if (!preg_match(
            '[window\.SSR\.renderContext=JSON\.parse\((".+(?<!\\\\)(?:\\\\\\\\)*")]',
            $scripts->text(normalizeWhitespace: false),
            $matches
        )) {
            throw new ParserException('Invalid games list.', ParserException::INVALID_GAMES_LIST);
        }

        $queries = json_decode(
            json_decode(
                json_decode($matches[1], flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE),
                flags: JSON_THROW_ON_ERROR
            )->queryData,
            true,
            flags: JSON_THROW_ON_ERROR
        )['queries'];

        $linkDetails = self::findQuery($queries, 'PlayerLinkDetails');
        if ($linkDetails['public_data']['visibility_state'] < 3) {
            throw new ParserException(
                'Games list is private or friends-only.',
                ParserException::NON_PUBLIC,
            );
        }

        if (!count($games = self::findQuery($queries, 'OwnedGames') ?? [])) {
            throw new ParserException(
                'Empty games list. This usually indicates games are private.',
                ParserException::EMPTY_GAMES_LIST,
            );
        }

        yield from $games;
    }

    private static function findQuery(array $queries, string $key): ?array
    {
        return array_find($queries, static fn ($query) => $query['queryKey'][0] === $key)['state']['data'];
    }
}
