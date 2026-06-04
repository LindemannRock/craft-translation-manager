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
 * Pins the contract of `TranslationsService::importPhpEntries()` — the shared
 * core behind both the CP PHP import and the console `translations/import`
 * command. A parsed `source => translation` entry must persist the value for
 * the import language (status `translated`), fan out a row to every unique
 * language, and give the source language the key as its own translation.
 *
 * Regression guard for the console import that previously dropped the value
 * (audit 4.3).
 *
 * @since 5.25.0
 */
final class ImportPhpEntriesTest extends TestCase
{
    public function testImportPersistsValueAndFansOutAcrossLanguages(): void
    {
        $this->requireAtLeastOneSite();

        $languages = TranslationManager::getInstance()->getUniqueLanguages();
        $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;

        // Prefer a non-source import language so the value vs source-key branches
        // are both exercised; fall back to the source language on single-language installs.
        $importLanguage = $sourceLanguage;
        foreach ($languages as $language) {
            if ($language !== $sourceLanguage) {
                $importLanguage = $language;
                break;
            }
        }

        $key = self::MARKER . 'import_' . bin2hex(random_bytes(4));
        $value = 'VALUE_' . bin2hex(random_bytes(3));

        $result = $this->translations->importPhpEntries(
            [['key' => $key, 'value' => $value]],
            $importLanguage,
            'site',
            null,
        );

        self::assertSame(1, $result['imported'], 'The import-language row should be counted as imported.');
        self::assertSame([], $result['errors']);

        $rows = [];
        foreach ($this->fetchRowsForSource($key) as $row) {
            $rows[$row['language']] = $row;
        }

        self::assertCount(count($languages), $rows, 'One row should be created per unique language.');

        // The import language carries the actual translated value.
        self::assertArrayHasKey($importLanguage, $rows);
        self::assertSame($value, $rows[$importLanguage]['translation']);
        self::assertSame('translated', $rows[$importLanguage]['status']);

        // The source language gets the key as its own translation.
        if ($importLanguage !== $sourceLanguage && isset($rows[$sourceLanguage])) {
            self::assertSame($key, $rows[$sourceLanguage]['translation']);
            self::assertSame('translated', $rows[$sourceLanguage]['status']);
        }
    }
}
