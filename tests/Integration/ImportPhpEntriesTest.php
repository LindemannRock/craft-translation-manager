<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
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

    public function testImportUpdatesOnlyExistingImportLanguageRow(): void
    {
        $this->requireAtLeastOneSite();

        $languages = TranslationManager::getInstance()->getUniqueLanguages();
        if (count($languages) < 2) {
            self::markTestSkipped('Test requires at least two unique languages.');
        }

        $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;
        $importLanguage = $languages[0] === $sourceLanguage ? $languages[1] : $languages[0];
        $otherLanguage = $languages[0] === $importLanguage ? $languages[1] : $languages[0];

        $key = self::MARKER . 'existing_' . bin2hex(random_bytes(4));
        foreach ($languages as $language) {
            $this->createTranslationRecord(
                $key,
                $language === $importLanguage ? 'Old import value' : 'Keep ' . $language,
                $language,
            );
        }

        $result = $this->translations->importPhpEntries(
            [['key' => $key, 'value' => 'New import value']],
            $importLanguage,
            'site',
            123,
        );

        self::assertSame(0, $result['imported']);
        self::assertSame(1, $result['updated']);
        self::assertSame([], $result['errors']);

        $rows = [];
        foreach ($this->fetchRowsForSource($key) as $row) {
            $rows[$row['language']] = $row;
        }

        self::assertSame('New import value', $rows[$importLanguage]['translation']);
        self::assertSame('translated', $rows[$importLanguage]['status']);
        self::assertSame('import', $rows[$importLanguage]['translationOrigin']);
        self::assertSame(123, (int)$rows[$importLanguage]['createdByUserId']);
        self::assertSame('Keep ' . $otherLanguage, $rows[$otherLanguage]['translation']);
        self::assertSame('manual', $rows[$otherLanguage]['translationOrigin']);
    }

    private function createTranslationRecord(string $source, string $translation, string $language): TranslationRecord
    {
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->translationKey = $source;
        $record->translation = $translation;
        $record->language = $language;
        $record->category = 'site';
        $record->context = 'site.php-import';
        $record->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        $record->lastUsed = Db::prepareDateForDb(new \DateTime());
        $record->dateCreated = Db::prepareDateForDb(new \DateTime());
        $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
        $record->uid = StringHelper::UUID();

        self::assertTrue($record->save(), json_encode($record->getErrors()));

        return $record;
    }
}
