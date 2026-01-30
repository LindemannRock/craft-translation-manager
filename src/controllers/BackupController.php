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
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use lindemannrock\base\helpers\DateTimeHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Backup Controller
 *
 * @since 1.0.0
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
        // Check granular permissions based on action
        $user = Craft::$app->getUser();

        switch ($action->id) {
            case 'create':
                if (!$user->checkPermission('translationManager:createBackups')) {
                    throw new ForbiddenHttpException('User does not have permission to create backups');
                }
                break;
            case 'restore':
                if (!$user->checkPermission('translationManager:restoreBackups')) {
                    throw new ForbiddenHttpException('User does not have permission to restore backups');
                }
                break;
            case 'delete':
                if (!$user->checkPermission('translationManager:deleteBackups')) {
                    throw new ForbiddenHttpException('User does not have permission to delete backups');
                }
                break;
            case 'download':
                if (!$user->checkPermission('translationManager:downloadBackups')) {
                    throw new ForbiddenHttpException('User does not have permission to download backups');
                }
                break;
            default:
                // Index/view - allow if user has ANY backup-related permission
                $hasAccess =
                    $user->checkPermission('translationManager:manageBackups') ||
                    $user->checkPermission('translationManager:createBackups') ||
                    $user->checkPermission('translationManager:downloadBackups') ||
                    $user->checkPermission('translationManager:restoreBackups') ||
                    $user->checkPermission('translationManager:deleteBackups');

                if (!$hasAccess) {
                    throw new ForbiddenHttpException('User does not have permission to manage backups');
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * List all backups (page render only - data loaded via AJAX)
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/backups/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Get backups list as JSON (for async loading)
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionGetBackups(): Response
    {
        $this->requireAcceptsJson();

        try {
            $backupService = TranslationManager::getInstance()->backup;
            $backups = $backupService->getBackups();
            $view = Craft::$app->getView();

            // Format file sizes and dates for display
            $craftTimezone = Craft::$app->getTimeZone();

            foreach ($backups as &$backup) {
                $backup['formattedSize'] = $backupService->formatBytes($backup['size']);

                // Convert timestamp to Craft's timezone and format directly
                $dateTime = new \DateTime('@' . $backup['timestamp']);
                $dateTime->setTimezone(new \DateTimeZone($craftTimezone));

                // Format date for display (base helper)
                $backup['formattedDate'] = DateTimeHelper::formatDatetime($dateTime);

                $typeInfo = $this->_formatBackupType($backup['reason'] ?? 'manual');
                $backup['typeLabel'] = $typeInfo['label'];

                $backup['typeBadgeHtml'] = $view->renderTemplate('lindemannrock-base/_components/badge', [
                    'label' => $typeInfo['label'],
                    'value' => $typeInfo['value'],
                    'colorSet' => 'backupReason',
                ]);

                $downloadUrl = UrlHelper::actionUrl('translation-manager/backup/download', [
                    'backup' => $backup['name'] ?? '',
                ]);

                $backup['rowActionsHtml'] = $view->renderTemplate('lindemannrock-base/_components/row-actions', [
                    'item' => $backup,
                    'actions' => [
                        'type' => 'menu',
                        'icon' => 'settings',
                        'items' => [
                            [
                                'label' => Craft::t('translation-manager', 'Restore'),
                                'class' => 'restore-backup',
                                'jsAction' => 'restore',
                                'permission' => 'translationManager:restoreBackups',
                                'data' => [
                                    'backup' => $backup['name'] ?? '',
                                ],
                            ],
                            [
                                'label' => Craft::t('translation-manager', 'Download ZIP'),
                                'url' => $downloadUrl,
                                'permission' => 'translationManager:downloadBackups',
                            ],
                            ['type' => 'divider'],
                            [
                                'label' => Craft::t('translation-manager', 'Delete'),
                                'class' => 'delete-backup error',
                                'jsAction' => 'delete',
                                'permission' => 'translationManager:deleteBackups',
                                'data' => [
                                    'backup' => $backup['name'] ?? '',
                                ],
                            ],
                        ],
                    ],
                ]);
            }

            return $this->asJson([
                'success' => true,
                'backups' => $backups,
            ]);
        } catch (\Exception $e) {
            $this->logError('Failed to fetch backups', ['error' => $e->getMessage()]);

            return $this->asJson([
                'success' => false,
                'error' => 'Failed to load backups: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Create a new backup
     *
     * @return Response
     * @since 1.0.0
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
                    'path' => basename($backupResult),
                ]);
            }
            
            // Check if failure was due to no translations
            $translations = TranslationManager::getInstance()->translations->getTranslations();
            if (empty($translations)) {
                return $this->asJson([
                    'success' => false,
                    'error' => 'No translations to backup. Add some translations first.',
                    'isEmpty' => true,
                ]);
            }
            
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to create backup',
            ]);
        } catch (\Exception $e) {
            $this->logError('Backup creation failed', ['error' => $e->getMessage()]);
            return $this->asJson([
                'success' => false,
                'error' => 'Failed to create backup: ' . $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Restore from a backup
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionRestore(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $backupName = Craft::$app->getRequest()->getRequiredBodyParam('backup');
        
        if (!preg_match('/^([\w]+\/)?(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})$/', $backupName)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid backup name format',
            ]);
        }

        $this->logInfo("User requested backup restore", ['backup' => $backupName]);
        $result = TranslationManager::getInstance()->backup->restoreBackup($backupName);
        
        return $this->asJson($result);
    }
    
    /**
     * Delete a backup
     *
     * @return Response
     * @since 1.0.0
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
                'error' => 'Invalid backup name format',
            ]);
        }

        $this->logInfo("User requested backup deletion", ['backup' => $backupName]);
        $success = TranslationManager::getInstance()->backup->deleteBackup($backupName);

        if ($success) {
            $this->logInfo("Backup deletion completed successfully", ['backup' => $backupName]);
            return $this->asJson([
                'success' => true,
                'message' => 'Backup deleted successfully',
            ]);
        }

        $this->logWarning("Backup deletion failed", ['backup' => $backupName]);
        return $this->asJson([
            'success' => false,
            'error' => 'Failed to delete backup',
        ]);
    }
    
    /**
     * Download a backup
     *
     * @return Response
     * @since 1.0.0
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
    private function _formatBackupType(string $reason): array
    {
        return match ($reason) {
            'before_import', 'before_php_import' => [
                'label' => Craft::t('translation-manager', 'Import'),
                'value' => 'import',
            ],
            'before_restore' => [
                'label' => Craft::t('translation-manager', 'Restore'),
                'value' => 'restore',
            ],
            'scheduled' => [
                'label' => Craft::t('translation-manager', 'Scheduled'),
                'value' => 'scheduled',
            ],
            'manual' => [
                'label' => Craft::t('translation-manager', 'Manual'),
                'value' => 'manual',
            ],
            'before_clear_all',
            'before_clear_formie',
            'before_clear_site',
            'before_clear' => [
                'label' => Craft::t('translation-manager', 'Clear'),
                'value' => 'clear',
            ],
            'before_cleanup',
            'before_cleanup_all',
            'before_cleanup_formie',
            'before_cleanup_site' => [
                'label' => Craft::t('translation-manager', 'Maintenance'),
                'value' => 'maintenance',
            ],
            default => [
                'label' => Craft::t('translation-manager', 'Other'),
                'value' => 'other',
            ],
        };
    }
}
