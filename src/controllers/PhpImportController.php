<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for importing translations from PHP files
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\helpers\PhpTranslationsHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * PHP Import Controller
 *
 * @since 1.0.0
 */
class PhpImportController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // PHP import is only available in devMode (for client onboarding scenarios)
        if (!Craft::$app->getConfig()->getGeneral()->devMode) {
            throw new ForbiddenHttpException('PHP import is only available in devMode');
        }

        if (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to import translations');
        }

        return parent::beforeAction($action);
    }

    /**
     * Get available PHP files for import (AJAX)
     */
    public function actionGetFiles(): Response
    {
        $files = PhpTranslationsHelper::findFiles();

        return $this->asJson([
            'success' => true,
            'files' => $files,
        ]);
    }

    /**
     * Preview PHP file contents before import (AJAX)
     */
    public function actionPreview(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $filePath = $request->getRequiredBodyParam('file');
        $language = $request->getRequiredBodyParam('language');
        $category = $request->getRequiredBodyParam('category');

        $results = PhpTranslationsHelper::parseAndCompare($filePath, $language, $category);

        return $this->asJson([
            'success' => true,
            'new' => $results['new'],
            'existing' => $results['existing'],
            'unchanged' => $results['unchanged'],
            'counts' => [
                'new' => count($results['new']),
                'existing' => count($results['existing']),
                'unchanged' => count($results['unchanged']),
            ],
        ]);
    }

    /**
     * Import selected translations from PHP file
     * Creates records for ALL site languages (like scan does)
     */
    public function actionImport(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $translations = $request->getRequiredBodyParam('translations');
        $importLanguage = $request->getRequiredBodyParam('language');
        $category = $request->getRequiredBodyParam('category');

        $settings = TranslationManager::getInstance()->getSettings();
        $sourceLanguage = $settings->sourceLanguage;

        // Get ALL unique site languages (like scan does)
        $allLanguages = TranslationManager::getInstance()->getUniqueLanguages();

        $imported = 0;
        $updated = 0;
        $errors = [];

        foreach ($translations as $item) {
            try {
                $key = $item['key'] ?? '';
                $value = $item['value'] ?? '';

                if (empty($key)) {
                    continue;
                }

                $sourceHash = md5($key);

                // Create/update records for ALL languages (like scan does)
                foreach ($allLanguages as $language) {
                    /** @var TranslationRecord|null $record */
                    $record = TranslationRecord::find()
                        ->where([
                            'sourceHash' => $sourceHash,
                            'language' => $language,
                            'category' => $category,
                        ])
                        ->one();

                    // Determine translation value and status for this language
                    $isImportLanguage = ($language === $importLanguage);
                    $isSourceLang = $this->isSourceLanguage($language, $sourceLanguage);

                    if ($record instanceof TranslationRecord) {
                        // Only update if this is the import language
                        if ($isImportLanguage) {
                            $record->translation = $value;
                            $record->status = !empty($value) ? 'translated' : 'pending';
                            $record->dateUpdated = Db::prepareDateForDb(new \DateTime());

                            if ($record->save()) {
                                $updated++;
                            } else {
                                $errors[] = "Failed to update '{$key}' ({$language}): " . json_encode($record->getErrors());
                            }
                        }
                        // Other languages: record exists, don't touch it
                    } else {
                        // Create new record for this language
                        $record = new TranslationRecord();
                        $record->source = $key;
                        $record->sourceHash = $sourceHash;
                        $record->translationKey = $key;
                        $record->language = $language;
                        $record->category = $category;
                        $record->context = 'site.php-import';
                        $record->siteId = $this->getSiteIdForLanguage($language);
                        $record->usageCount = 1;
                        $record->dateCreated = Db::prepareDateForDb(new \DateTime());
                        $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
                        $record->uid = StringHelper::UUID();

                        if ($isImportLanguage) {
                            // This is the language being imported - use the value
                            $record->translation = $value;
                            $record->status = !empty($value) ? 'translated' : 'pending';
                        } elseif ($isSourceLang) {
                            // Source language: key is the translation
                            $record->translation = $key;
                            $record->status = 'translated';
                        } else {
                            // Other languages: empty translation, pending
                            $record->translation = '';
                            $record->status = 'pending';
                        }

                        if ($record->save()) {
                            // Only count the import language for the "imported" count
                            if ($isImportLanguage) {
                                $imported++;
                            }
                        } else {
                            $errors[] = "Failed to create '{$key}' ({$language}): " . json_encode($record->getErrors());
                        }
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Error processing: " . ($item['key'] ?? 'unknown') . " - " . $e->getMessage();
            }
        }

        $this->logInfo('PHP import completed', [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => count($errors),
            'language' => $importLanguage,
            'category' => $category,
            'languages_created' => $allLanguages,
        ]);

        return $this->asJson([
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'message' => "Imported {$imported} new, updated {$updated} existing translations",
        ]);
    }

    /**
     * Check if a language matches the configured source language
     */
    private function isSourceLanguage(string $language, string $sourceLanguage): bool
    {
        if ($language === $sourceLanguage) {
            return true;
        }

        // Base language match (e.g., 'en-US' matches source 'en')
        $languageBase = explode('-', $language)[0];
        $sourceBase = explode('-', $sourceLanguage)[0];

        return $languageBase === $sourceBase;
    }

    /**
     * Get a site ID for a given language (for backwards compatibility)
     */
    private function getSiteIdForLanguage(string $language): int
    {
        $sites = Craft::$app->getSites()->getAllSites();
        foreach ($sites as $site) {
            if ($site->language === $language) {
                return $site->id;
            }
        }
        // Fallback to primary site
        return Craft::$app->getSites()->getPrimarySite()->id;
    }
}
