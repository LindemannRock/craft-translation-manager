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

    public function testCategoryScopedScanDoesNotTouchOtherCategories(): void
    {
        $this->requireAtLeastOneSite();

        $templatesPath = Craft::getAlias('@templates');
        if (!is_string($templatesPath) || !is_dir($templatesPath) || !is_writable($templatesPath)) {
            self::markTestSkipped('Test requires a writable @templates path.');
        }

        $settings = TranslationManager::getInstance()->getSettings();
        $originalCategories = $settings->translationCategories;
        $originalCategory = $settings->translationCategory;
        $categoryA = 'tm_test_a_' . bin2hex(random_bytes(4));
        $categoryB = 'tm_test_b_' . bin2hex(random_bytes(4));
        $templateFile = $templatesPath . DIRECTORY_SEPARATOR . $categoryA . '.twig';
        $sourceA = self::MARKER . 'scan_category_a_' . bin2hex(random_bytes(4));
        $sourceB = self::MARKER . 'scan_category_b_' . bin2hex(random_bytes(4));
        $language = Craft::$app->getSites()->getPrimarySite()->language;

        try {
            $settings->translationCategories = [
                ['key' => $categoryA, 'enabled' => true],
                ['key' => $categoryB, 'enabled' => true],
            ];
            $settings->translationCategory = $categoryA;

            $this->createTranslationRecord($sourceA, 'Category A translation', $language, $categoryA, 'site.old-template', 'translated');
            $this->createTranslationRecord($sourceB, 'Category B translation', $language, $categoryB, 'site.old-template', 'translated');

            self::assertNotFalse(file_put_contents($templateFile, "{{ '{$sourceA}'|t('{$categoryA}') }}\n"));

            $result = $this->translations->scanTemplatesForUnused([$categoryA]);

            self::assertSame(0, $result['marked_unused']);
            self::assertSame([], $result['errors']);

            $categoryARows = $this->fetchRowsForSource($sourceA);
            self::assertCount(1, $categoryARows);
            self::assertSame('translated', $categoryARows[0]['status']);

            $categoryBRows = $this->fetchRowsForSource($sourceB);
            self::assertCount(1, $categoryBRows);
            self::assertSame('translated', $categoryBRows[0]['status']);
        } finally {
            $settings->translationCategories = $originalCategories;
            $settings->translationCategory = $originalCategory;
            if (is_file($templateFile)) {
                unlink($templateFile);
            }
            TranslationRecord::deleteAll(['category' => [$categoryA, $categoryB]]);
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
