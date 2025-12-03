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
        // Require permission to view translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to access import/export');
        }

        return parent::beforeAction($action);
    }

    /**
     * Import/Export index page
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        // Get import history
        /** @var \lindemannrock\translationmanager\records\ImportHistoryRecord[] $history */
        $history = \lindemannrock\translationmanager\records\ImportHistoryRecord::find()
            ->with('user')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit(10) // Show last 10 imports like we do with backups
            ->all();

        // Format the data for display
        $formattedHistory = [];
        foreach ($history as $record) {
            $formattedHistory[] = [
                'id' => $record->id,
                'filename' => $record->filename,
                'filesize' => Craft::$app->getFormatter()->asShortSize($record->filesize),
                'imported' => $record->imported,
                'updated' => $record->updated,
                'skipped' => $record->skipped,
                'errors' => $record->errors ? json_decode($record->errors, true) : [],
                'hasErrors' => !empty($record->errors),
                'backupPath' => $record->backupPath,
                'user' => $record->user->username ?? 'Unknown',
                'dateCreated' => $record->dateCreated,
                'formattedDate' => Craft::$app->getFormatter()->asDatetime($record->dateCreated, 'short'),
            ];
        }

        // Get total count for "View All" link
        $totalImports = \lindemannrock\translationmanager\records\ImportHistoryRecord::find()->count();

        return $this->renderTemplate('translation-manager/import-export/index', [
            'settings' => $settings,
            'importHistory' => $formattedHistory,
            'totalImports' => $totalImports,
            'allSites' => TranslationManager::getInstance()->getAllowedSites(),
        ]);
    }
}
