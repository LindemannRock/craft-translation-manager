<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins the happy-path contract of `TranslationsService::createOrUpdateTranslation()`:
 * a source-language string in a known context fans out to one row per unique
 * site language, with the expected sourceHash, default category, and an
 * initial usageCount of 1.
 *
 * @since 5.24.0
 */
final class TranslationsCreateOrUpdateHappyPathTest extends TestCase
{
    public function testFanOutCreatesOneRowPerUniqueLanguage(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $source = self::MARKER . 'happy_path_' . bin2hex(random_bytes(4));
        $expectedHash = md5($source);
        $expectedLanguages = TranslationManager::getInstance()->getUniqueLanguages();
        $expectedCategory = TranslationManager::getInstance()
            ->getSettings()
            ->getPrimaryCategory();

        $primary = $this->translations->createOrUpdateTranslation($source, 'site');

        self::assertNotNull($primary, 'Happy-path source string should produce at least one row.');

        $rows = $this->fetchRowsForSource($source);
        self::assertCount(
            count($expectedLanguages),
            $rows,
            'One row per unique site language is expected after first call.',
        );

        foreach ($rows as $row) {
            self::assertSame($expectedHash, $row['sourceHash']);
            self::assertSame($expectedCategory, $row['category']);
            self::assertSame('site', $row['context']);
            self::assertSame('system', $row['translationOrigin']);
            self::assertSame(1, (int) $row['usageCount']);
        }

        $foundLanguages = array_column($rows, 'language');
        sort($foundLanguages);
        $expectedSorted = $expectedLanguages;
        sort($expectedSorted);
        self::assertSame(
            $expectedSorted,
            $foundLanguages,
            'Row languages should match the unique site languages exactly.',
        );
    }
}
