<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing translation backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use craft\helpers\FileHelper;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Backup Controller
 */
class BackupController extends Controller
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
        // Most actions require edit permissions
        $actionsRequiringEdit = ['create', 'restore', 'delete'];
        
        if (in_array($action->id, $actionsRequiringEdit)) {
            // Allow if user has edit translations OR edit settings permission
            if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations') && 
                !Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
                throw new ForbiddenHttpException('User does not have permission to manage backups');
            }
        } else {
            // View/download require at least view permission
            if (!Craft::$app->getUser()->checkPermission('translationManager:viewTranslations') && 
                !Craft::$app->getUser()->checkPermission('translationManager:editSettings')) {
                throw new ForbiddenHttpException('User does not have permission to view backups');
            }
        }

        return parent::beforeAction($action);
    }

    /**
     * List all backups
     */
    public function actionIndex(): Response
    {
        $backupService = TranslationManager::getInstance()->backup;
        $backups = $backupService->getBackups();
        
        // Format file sizes and dates for display
        $craftTimezone = Craft::$app->getTimeZone();

        // Debug: log the timezone info
        $this->logDebug("Craft timezone", ['timezone' => $craftTimezone]);

        foreach ($backups as &$backup) {
            $backup['formattedSize'] = $backupService->formatBytes($backup['size']);

            // Convert timestamp to Craft's timezone and format directly
            $dateTime = new \DateTime('@' . $backup['timestamp']); // Create from timestamp (UTC)
            $originalTime = $dateTime->format('Y-m-d H:i:s T');

            $dateTime->setTimezone(new \DateTimeZone($craftTimezone)); // Convert to Craft timezone
            $convertedTime = $dateTime->format('Y-m-d H:i:s T');

            // Debug logging
            $this->logDebug("Timestamp conversion", [
                'timestamp' => $backup['timestamp'],
                'original' => $originalTime,
                'converted' => $convertedTime
            ]);

            // Format directly with PHP to avoid Craft's locale formatting
            $backup['formattedDate'] = $dateTime->format('n/j/Y, g:i A') . ' (' . $craftTimezone . ')';

            // Format reason for display
            $backup['formattedReason'] = $this->_formatBackupReason($backup['reason'] ?? 'manual');
        }
        
        // Add debug info for troubleshooting
        $debugInfo = null;
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            $settings = TranslationManager::getInstance()->getSettings();
            $debugInfo = [
                'backupVolumeUid' => $settings->backupVolumeUid,
                'backupPath' => $settings->getBackupPath(),
                'totalBackups' => count($backups),
                'craftTimezone' => $craftTimezone
            ];
        }

        return $this->renderTemplate('translation-manager/backups/index', [
            'backups' => $backups,
            'debugInfo' => $debugInfo,
        ]);
    }
    
    /**
     * Create a new backup
     */
    public function actionCreate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        try {
            $reason = Craft::$app->getRequest()->getBodyParam('reason', 'manual');

            $this->logInfo("User requested manual backup creation");

            $backupResult = TranslationManager::getInstance()->backup->createBackup($reason);
            
            if ($backupResult) {
                return $this->asJson([
                    'success' => true,
                    'message' => 'Backup created successfully',
                    'path' => basename($backupResult)
                ]);
            }
            
            // Check if failure was due to no translations
            $translations = TranslationManager::getInstance()->translations->getTranslations();
            if (empty($translations)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No translations to backup. Add some translations first.',
                    'isEmpty' => true
                ]);
            }
            
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to create backup'
            ]);
        } catch (\Exception $e) {
            $this->logError('Backup creation failed', ['error' => $e->getMessage()]);
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to create backup: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Restore from a backup
     */
    public function actionRestore(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $backupName = Craft::$app->getRequest()->getRequiredBodyParam('backup');
        
        if (!preg_match('/^([\w]+\/)?(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})$/', $backupName)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid backup name format'
            ]);
        }

        $this->logInfo("User requested backup restore", ['backup' => $backupName]);
        $result = TranslationManager::getInstance()->backup->restoreBackup($backupName);
        
        return $this->asJson($result);
    }
    
    /**
     * Delete a backup
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $backupName = Craft::$app->getRequest()->getRequiredBodyParam('backup');

        if (!preg_match('/^([\w]+\/)?(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})$/', $backupName)) {
            $this->logWarning("Invalid backup name format attempted", ['backup' => $backupName]);
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid backup name format'
            ]);
        }

        $this->logInfo("User requested backup deletion", ['backup' => $backupName]);
        $success = TranslationManager::getInstance()->backup->deleteBackup($backupName);

        if ($success) {
            $this->logInfo("Backup deletion completed successfully", ['backup' => $backupName]);
            return $this->asJson([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        }

        $this->logWarning("Backup deletion failed", ['backup' => $backupName]);
        return $this->asJson([
            'success' => false,
            'error' => 'Failed to delete backup'
        ]);
    }
    
    /**
     * Download a backup
     */
    public function actionDownload(): Response
    {
        $backupName = Craft::$app->getRequest()->getRequiredParam('backup');

        if (!preg_match('/^([\w]+\/)?(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})$/', $backupName)) {
            throw new NotFoundHttpException('Invalid backup name');
        }

        $this->logInfo("User requested backup download", ['backup' => $backupName]);

        $backupService = TranslationManager::getInstance()->backup;
        $settings = TranslationManager::getInstance()->getSettings();

        // Check if using volume storage
        $useVolume = !empty($settings->backupVolumeUid);

        if ($useVolume) {
            return $this->_downloadVolumeBackup($backupName);
        } else {
            return $this->_downloadLocalBackup($backupName);
        }
    }

    /**
     * Download backup from volume storage
     */
    private function _downloadVolumeBackup(string $backupName): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $volume = Craft::$app->getVolumes()->getVolumeByUid($settings->backupVolumeUid);

        if (!$volume) {
            throw new NotFoundHttpException('Volume not found');
        }

        $fs = $volume->getFs();
        $backupPath = 'translation-manager/backups/' . $backupName;

        if (!$fs->directoryExists($backupPath)) {
            throw new NotFoundHttpException('Backup not found');
        }

        // Create a temporary zip file
        $zipPath = Craft::$app->getPath()->getTempPath() . '/translation-backup-' . str_replace('/', '-', $backupName) . '.zip';

        // Create zip archive
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        // Add backup files to zip
        $files = ['metadata.json', 'formie-translations.json', 'site-translations.json'];
        foreach ($files as $file) {
            $filePath = $backupPath . '/' . $file;
            if ($fs->fileExists($filePath)) {
                $content = $fs->read($filePath);
                $zip->addFromString($file, $content);
            }
        }

        $zip->close();

        // Send the file
        $response = Craft::$app->getResponse();
        $response->sendFile($zipPath, 'translation-backup-' . str_replace('/', '-', $backupName) . '.zip', [
            'mimeType' => 'application/zip',
            'inline' => false,
        ]);

        // Clean up temp file after sending
        register_shutdown_function(function() use ($zipPath) {
            @unlink($zipPath);
        });

        return $response;
    }

    /**
     * Download backup from local storage
     */
    private function _downloadLocalBackup(string $backupName): Response
    {
        $backupService = TranslationManager::getInstance()->backup;
        $backupDir = $backupService->getBackupPath() . '/' . $backupName;

        if (!is_dir($backupDir)) {
            throw new NotFoundHttpException('Backup not found');
        }

        // Create a temporary zip file
        $zipPath = Craft::$app->getPath()->getTempPath() . '/translation-backup-' . str_replace('/', '-', $backupName) . '.zip';

        // Create zip archive
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        // Add all files from backup directory
        $files = FileHelper::findFiles($backupDir);
        foreach ($files as $file) {
            $relativePath = str_replace($backupDir . '/', '', $file);
            $zip->addFile($file, $relativePath);
        }

        $zip->close();

        // Send the file
        $response = Craft::$app->getResponse();
        $response->sendFile($zipPath, 'translation-backup-' . str_replace('/', '-', $backupName) . '.zip', [
            'mimeType' => 'application/zip',
            'inline' => false,
        ]);

        // Clean up temp file after sending
        register_shutdown_function(function() use ($zipPath) {
            @unlink($zipPath);
        });

        return $response;
    }

    /**
     * Format backup reason for display
     */
    private function _formatBackupReason(string $reason): string
    {
        return match($reason) {
            'manual' => Craft::t('translation-manager', 'Manual'),
            'before_import' => Craft::t('translation-manager', 'Before Import'),
            'before_restore' => Craft::t('translation-manager', 'Before Restore'),
            'scheduled' => Craft::t('translation-manager', 'Scheduled'),
            'before_clear_all' => Craft::t('translation-manager', 'Before Clear All'),
            'before_clear_formie' => Craft::t('translation-manager', 'Before Clear Formie'),
            'before_clear_site' => Craft::t('translation-manager', 'Before Clear Site'),
            'before_cleanup' => Craft::t('translation-manager', 'Before Cleanup'),
            'before_clear' => Craft::t('translation-manager', 'Before Clear'),
            default => Craft::t('translation-manager', ucfirst(str_replace('_', ' ', $reason)))
        };
    }
}