<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use Symfony\Component\DomCrawler\Crawler;

final class UserGamesParser
{
    public static function parse(Crawler $crawler): iterable
    {
        $scripts = $crawler->filter('script#valve-ssr-data');

        if (count($scripts) !== 1) {
            throw new ParserException(
                'Unexpected page content.',
                ParserException::UNEXPECTED_CONTENT,
            );
        }

        $queries = self::decodeQueries($scripts->text(normalizeWhitespace: false));

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

    private static function decodeQueries(string $json): array
    {
        try {
            $ssrData = json_decode(
                $json,
                flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            if (
                !is_object($ssrData)
                || !isset($ssrData->renderContext)
                || !is_object($ssrData->renderContext)
                || !isset($ssrData->renderContext->queryData)
                || !is_string($ssrData->renderContext->queryData)
            ) {
                throw new ParserException('Invalid games list.', ParserException::INVALID_GAMES_LIST);
            }

            $queryData = json_decode($ssrData->renderContext->queryData, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ParserException(
                'Invalid games list.',
                ParserException::INVALID_GAMES_LIST,
                $exception,
            );
        }

        if (!is_array($queryData) || !isset($queryData['queries']) || !is_array($queryData['queries'])) {
            throw new ParserException('Invalid games list.', ParserException::INVALID_GAMES_LIST);
        }

        return $queryData['queries'];
    }

    private static function findQuery(array $queries, string $key): ?array
    {
        return array_find($queries, static fn ($query) => $query['queryKey'][0] === $key)['state']['data'];
    }
}
