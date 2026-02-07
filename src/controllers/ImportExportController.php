<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing import/export functionality
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use lindemannrock\base\helpers\CsvImportHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Import/Export Controller
 *
 * @since 1.0.0
 */
class ImportExportController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission to import or export translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations') &&
            !Craft::$app->getUser()->checkPermission('translationManager:exportTranslations') &&
            !Craft::$app->getUser()->checkPermission('translationManager:viewImportHistory') &&
            !Craft::$app->getUser()->checkPermission('translationManager:clearImportHistory')) {
            throw new ForbiddenHttpException('User does not have permission to access import/export');
        }

        return parent::beforeAction($action);
    }

    /**
     * Import/Export index page
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $user = Craft::$app->getUser();
        $canViewHistory = $user->checkPermission('translationManager:manageImportExport') ||
            $user->checkPermission('translationManager:viewImportHistory');

        // Get import history and format for display
        $formattedHistory = [];
        if ($canViewHistory) {
            /** @var \lindemannrock\translationmanager\records\ImportHistoryRecord[] $history */
            $history = \lindemannrock\translationmanager\records\ImportHistoryRecord::find()
                ->with('user')
                ->orderBy(['dateCreated' => SORT_DESC])
                ->limit(10) // Show last 10 imports like we do with backups
                ->all();

            foreach ($history as $record) {
                $errorsJson = (string) $record->errors;
                $errors = $errorsJson ? json_decode($errorsJson, true) : [];
                $failedCount = is_array($errors) ? count($errors) : 0;

                $formattedHistory[] = [
                    'id' => $record->id,
                    'filename' => $record->filename,
                    'formattedSize' => Craft::$app->getFormatter()->asShortSize($record->filesize),
                    'imported' => $record->imported,
                    'updated' => $record->updated,
                    'skipped' => $record->skipped,
                    'errors' => $errors,
                    'hasErrors' => !empty($errors),
                    'failed' => $failedCount,
                    'backupPath' => $record->backupPath,
                    'user' => $record->user->username ?? 'Unknown',
                    'dateCreated' => $record->dateCreated,
                    'formattedDate' => DateFormatHelper::formatDatetime($record->dateCreated),
                ];
            }
        }

        // Get total count for "View All" link
        $totalImports = $canViewHistory
            ? \lindemannrock\translationmanager\records\ImportHistoryRecord::find()->count()
            : 0;

        return $this->renderTemplate('translation-manager/import-export/index', [
            'settings' => $settings,
            'importHistory' => $formattedHistory,
            'totalImports' => $totalImports,
            'canViewHistory' => $canViewHistory,
            'uniqueLanguages' => TranslationManager::getInstance()->getUniqueLanguages(),
            'allSites' => TranslationManager::getInstance()->getAllowedSites(), // Legacy
            'importLimits' => [
                'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
                'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
            ],
        ]);
    }
}
