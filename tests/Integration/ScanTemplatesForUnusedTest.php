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
 * Pins the second-pass maintenance scan contract: existing site/runtime rows
 * are marked unused when missing from templates, and previously unused runtime
 * rows are reactivated with their discovered file context.
 *
 * @since 5.24.0
 */
final class ScanTemplatesForUnusedTest extends TestCase
{
    public function testScanMarksUnusedAndReactivatesRuntimeRows(): void
    {
        $this->requireAtLeastOneSite();

        $templatesPath = Craft::getAlias('@templates');
        if (!is_string($templatesPath) || !is_dir($templatesPath) || !is_writable($templatesPath)) {
            self::markTestSkipped('Test requires a writable @templates path.');
        }

        $settings = TranslationManager::getInstance()->getSettings();
        $originalCategories = $settings->translationCategories;
        $originalCategory = $settings->translationCategory;
        $category = 'tm_test_' . bin2hex(random_bytes(4));
        $templateFile = $templatesPath . DIRECTORY_SEPARATOR . $category . '.twig';
        $usedRuntimeSource = self::MARKER . 'scan_runtime_' . bin2hex(random_bytes(4));
        $missingSource = self::MARKER . 'scan_missing_' . bin2hex(random_bytes(4));
        $language = Craft::$app->getSites()->getPrimarySite()->language;

        try {
            $settings->translationCategories = [['key' => $category, 'enabled' => true]];
            $settings->translationCategory = $category;

            $this->createTranslationRecord($usedRuntimeSource, 'Runtime translation', $language, $category, 'runtime', 'unused');
            $this->createTranslationRecord($missingSource, 'Missing translation', $language, $category, 'site.old-template', 'translated');

            self::assertNotFalse(file_put_contents($templateFile, "{{ '{$usedRuntimeSource}'|t('{$category}') }}\n"));

            $result = $this->translations->scanTemplatesForUnused();

            self::assertSame(1, $result['marked_unused']);
            self::assertSame(1, $result['reactivated']);
            self::assertSame([], $result['errors']);

            $runtimeRows = $this->fetchRowsForSource($usedRuntimeSource);
            self::assertCount(1, $runtimeRows);
            self::assertSame('translated', $runtimeRows[0]['status']);
            self::assertSame('site.' . basename($templateFile), $runtimeRows[0]['context']);

            $missingRows = $this->fetchRowsForSource($missingSource);
            self::assertCount(1, $missingRows);
            self::assertSame('unused', $missingRows[0]['status']);
        } finally {
            $settings->translationCategories = $originalCategories;
            $settings->translationCategory = $originalCategory;
            if (is_file($templateFile)) {
                unlink($templateFile);
            }
            TranslationRecord::deleteAll(['category' => $category]);
        }
    }

    private function createTranslationRecord(
        string $source,
        string $translation,
        string $language,
        string $category,
        string $context,
        string $status,
    ): TranslationRecord {
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->translationKey = $source;
        $record->translation = $translation;
        $record->language = $language;
        $record->category = $category;
        $record->context = $context;
        $record->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record->status = $status;
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
