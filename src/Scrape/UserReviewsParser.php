<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Scrape;

use ScriptFUSION\StaticClass;
use Symfony\Component\DomCrawler\Crawler;

final class UserReviewsParser
{
    use StaticClass;

    public static function parse(Crawler $crawler): array
    {
        self::validate($crawler);

        return $crawler->filter('.review_box .title > a')->each(
            static function (Crawler $a): array {
                $href = $a->attr('href');

                if (!preg_match('[/(\d+)/$]', $href, $matches)) {
                    throw new ParserException("Could not parse app ID from \"$href\".");
                }

                return [
                    'app_id' => +$matches[1],
                    'url' => $href,
                    'positive' => $a->text() === 'Recommended',
                ];
            }
        );
    }

    private static function validate(Crawler $crawler): void
    {
        $bodyClasses = explode(' ', $crawler->filter('body')->attr('class'));

        if (!\in_array('migrated_profile_page', $bodyClasses, true)) {
            throw new ParserException(
                'Unexpected page type! Expected: migrated_profile_page.',
                ParserException::UNEXPECTED_TYPE
            );
        }
    }
}
