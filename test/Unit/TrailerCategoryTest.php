<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Provider\Steam\Scrape\TrailerCategory;

/**
 * @see TrailerCategory
 */
final class TrailerCategoryTest extends TestCase
{
    #[DataProvider('provideLabels')]
    public function testLabel(TrailerCategory $category, string $label): void
    {
        self::assertSame($label, $category->label());
    }

    public static function provideLabels(): iterable
    {
        yield [TrailerCategory::GAMEPLAY, 'Gameplay'];
        yield [TrailerCategory::TEASER, 'Teaser'];
        yield [TrailerCategory::GENERAL_CINEMATIC, 'General / Cinematic'];
        yield [TrailerCategory::INTERVIEW_DEV_DIARY, 'Interview / Dev Diary'];
    }

    #[DataProvider('provideUnknownIds')]
    public function testUnknownIdHasNoCategory(int $id): void
    {
        self::assertNull(TrailerCategory::tryFrom($id));
    }

    public static function provideUnknownIds(): iterable
    {
        // 0 means uncategorized. 4 and 5 have never been observed in the wild.
        yield [0];

        yield [4];
        yield [5];
        yield [7];
        yield [99];
    }
}
