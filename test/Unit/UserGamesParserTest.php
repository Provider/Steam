<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Scrape\ParserException;
use ScriptFUSION\Porter\Provider\Steam\Scrape\UserGamesParser;
use Symfony\Component\DomCrawler\Crawler;

final class UserGamesParserTest extends TestCase
{
    /**
     * Tests that when a games page contains Valve SSR data, the owned games are parsed.
     */
    public function testParseValveSsrData(): void
    {
        $games = [
            ['appid' => 1, 'name' => 'Alfa', 'playtime_forever' => 2],
        ];
        $queries = [
            [
                'queryKey' => ['PlayerLinkDetails'],
                'state' => ['data' => ['public_data' => ['visibility_state' => 3]]],
            ],
            [
                'queryKey' => ['OwnedGames'],
                'state' => ['data' => $games],
            ],
        ];
        $ssrData = json_encode([
            'renderContext' => [
                'queryData' => json_encode(['queries' => $queries], flags: JSON_THROW_ON_ERROR),
            ],
        ], flags: JSON_THROW_ON_ERROR);
        $crawler = new Crawler("<script type=\"application/json\" id=\"valve-ssr-data\">$ssrData</script>");

        self::assertSame($games, iterator_to_array(UserGamesParser::parse($crawler)));
    }

    /**
     * Tests that when Valve SSR data is malformed or incomplete, it is rejected as an invalid games list.
     */
    #[DataProvider('provideInvalidValveSsrData')]
    public function testInvalidValveSsrData(string $ssrData): void
    {
        $crawler = new Crawler("<script type=\"application/json\" id=\"valve-ssr-data\">$ssrData</script>");

        $this->expectException(ParserException::class);
        $this->expectExceptionCode(ParserException::INVALID_GAMES_LIST);

        iterator_to_array(UserGamesParser::parse($crawler));
    }

    public static function provideInvalidValveSsrData(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'missing query data' => ['{"renderContext":{}}'];
        yield 'missing queries' => [
            json_encode(['renderContext' => ['queryData' => '{}']], flags: JSON_THROW_ON_ERROR),
        ];
    }
}
