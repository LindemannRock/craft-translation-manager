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
use craft\web\Controller;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\helpers\PhpTranslationsHelper;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * PHP Import Controller
 *
 * @since 5.17.0
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'PHP import is only available in devMode.'));
        }

        if (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to import translations.'));
        }

        return parent::beforeAction($action);
    }

    /**
     * Get available PHP files for import (AJAX)
     *
     * @return Response
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
     *
     * @return Response
     */
    public function actionPreview(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $filePath = $request->getRequiredBodyParam('file');
        $language = $request->getRequiredBodyParam('language');
        $category = $request->getRequiredBodyParam('category');

        $categoryStatus = TranslationManager::getInstance()->translations->getImportCategoryStatus($category);
        if (isset($categoryStatus['error'])) {
            return $this->asJson([
                'success' => false,
                'error' => $categoryStatus['error'],
            ]);
        }

        $results = PhpTranslationsHelper::parseAndCompare($filePath, $language, $category);

        return $this->asJson([
            'success' => true,
            'new' => $results['new'],
            'existing' => $results['existing'],
            'unchanged' => $results['unchanged'],
            'categoryStatus' => $categoryStatus,
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
     *
     * @return Response
     */
    public function actionImport(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $translations = $request->getRequiredBodyParam('translations');
        $importLanguage = $request->getRequiredBodyParam('language');
        $category = $request->getRequiredBodyParam('category');
        $file = (string)$request->getBodyParam('file', '');

        $settings = TranslationManager::getInstance()->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;
        $categoryStatus = $translationsService->getImportCategoryStatus($category);

        if (isset($categoryStatus['error'])) {
            return $this->asJson([
                'success' => false,
                'error' => $categoryStatus['error'],
            ]);
        }

        if (($categoryStatus['requiresRegistration'] ?? false) && !$translationsService->registerImportCategory($category)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Category "{category}" is not enabled and cannot be added automatically because Translation Categories are configured in config/translation-manager.php.', [
                    'category' => $category,
                ]),
            ]);
        }

        $createBackup = (bool)$request->getBodyParam('createBackup', true);
        $backupPath = null;
        // Create backup before import if enabled
        if ($settings->backupEnabled && $settings->backupOnImport && $createBackup) {
            $backupPath = TranslationManager::getInstance()->backup->createBackup('before_php_import');
        }

        $result = $translationsService->importPhpEntries(
            $translations,
            $importLanguage,
            $category,
            Craft::$app->getUser()->getId(),
        );

        $imported = $result['imported'];
        $updated = $result['updated'];
        $errors = $result['errors'];

        // Regenerate translation files on disk so they reflect the imported
        // rows. Mirrors ImportController; the auto-generate setting gate lives
        // inside triggerAutoGenerate().
        TranslationManager::getInstance()->generate->triggerAutoGenerate();

        $this->saveImportHistory($file, $imported, $updated, $errors, $backupPath);

        return $this->asJson([
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'message' => Craft::t('translation-manager', 'Imported {imported} new, updated {updated} existing translations', ['imported' => $imported, 'updated' => $updated]),
        ]);
    }

    /**
     * Save PHP import history for the shared Import/Export history table.
     *
     * @param string[] $errors
     */
    private function saveImportHistory(string $file, int $imported, int $updated, array $errors, ?string $backupPath): void
    {
        $userId = Craft::$app->getUser()->getId();
        if (!$userId) {
            return;
        }

        $history = new ImportHistoryRecord();
        $history->userId = $userId;
        $history->filename = $file !== '' ? $file : 'PHP import';
        $history->filesize = 0;
        $history->imported = $imported;
        $history->updated = $updated;
        $history->skipped = 0;
        $history->errors = !empty($errors) ? json_encode($errors) : null;
        $history->backupPath = $backupPath ? basename($backupPath) : null;
        $history->save();
    }
}
