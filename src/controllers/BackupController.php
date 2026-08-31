<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing translation backups
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\SafeSegmentHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\TranslationManager;
use Throwable;
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
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to create backups.'));
                }
                break;
            case 'restore':
                if (!$user->checkPermission('translationManager:restoreBackups')) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to restore backups.'));
                }
                break;
            case 'delete':
                if (!$user->checkPermission('translationManager:deleteBackups')) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to delete backups.'));
                }
                break;
            case 'download':
                if (!$user->checkPermission('translationManager:downloadBackups')) {
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to download backups.'));
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
                    throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to manage backups.'));
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * List all backups (page render only - data loaded via AJAX)
     *
     * @return Response
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
                $backup['formattedDate'] = DateFormatHelper::formatDatetime($dateTime);

                $reason = $backup['reason'] ?? 'manual';
                $isScheduled = strtolower((string)$reason) === 'scheduled';
                if ($isScheduled) {
                    $backup['user'] = Craft::t('translation-manager', 'System');
                    $backup['userId'] = null;
                }

                $typeInfo = $this->_formatBackupType($reason);
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
                'error' => Craft::t('translation-manager', 'Failed to load backups: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }
    
    /**
     * Create a new backup
     *
     * @return Response
     */
    public function actionCreate(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        try {
            $reason = Craft::$app->getRequest()->getBodyParam('reason', 'manual');

            $this->logInfo("User requested manual backup creation");

            $backupResult = TranslationManager::getInstance()->backup->createBackup($reason);
            
            if ($backupResult !== null) {
                return $this->asJson([
                    'success' => true,
                    'message' => Craft::t('translation-manager', 'Backup created successfully'),
                    'path' => basename($backupResult),
                ]);
            }
            
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('translation-manager', 'No translations to backup. Add some translations first.'),
                'isEmpty' => true,
            ]);
        } catch (\Exception $e) {
            $this->logError('Backup creation failed', ['error' => $e->getMessage()]);
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Failed to create backup: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }
    
    /**
     * Restore from a backup
     *
     * @return Response
     */
    public function actionRestore(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $backupName = Craft::$app->getRequest()->getRequiredBodyParam('backup');
        
        if (!TranslationManager::getInstance()->backup->isValidBackupName($backupName)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid backup name format'),
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
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        $backupName = Craft::$app->getRequest()->getRequiredBodyParam('backup');

        if (!TranslationManager::getInstance()->backup->isValidBackupName($backupName)) {
            $this->logWarning("Invalid backup name format attempted", ['backup' => $backupName]);
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid backup name format'),
            ]);
        }

        $this->logInfo("User requested backup deletion", ['backup' => $backupName]);
        $success = TranslationManager::getInstance()->backup->deleteBackup($backupName);

        if ($success) {
            $this->logInfo("Backup deletion completed successfully", ['backup' => $backupName]);
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('translation-manager', 'Backup deleted successfully'),
            ]);
        }

        $this->logWarning("Backup deletion failed", ['backup' => $backupName]);
        return $this->asJson([
            'success' => false,
            'error' => Craft::t('translation-manager', 'Failed to delete backup'),
        ]);
    }
    
    /**
     * Download a backup
     *
     * @return Response
     */
    public function actionDownload(): Response
    {
        $backupName = Craft::$app->getRequest()->getRequiredParam('backup');

        $backupService = TranslationManager::getInstance()->backup;
        if (!$backupService->isValidBackupName($backupName)) {
            throw new NotFoundHttpException(Craft::t('translation-manager', 'Invalid backup name'));
        }

        $this->logInfo("User requested backup download", ['backup' => $backupName]);

        $downloadFilename = 'translation-backup-' . SafeSegmentHelper::filenamePart($backupName, 'backup') . '.zip';
        return $this->prepareOwnedBackupDownload($backupService, $backupName, $downloadFilename);
    }

    /** Allocate one request-owned temporary ZIP beneath Craft's temporary directory. */
    protected function createOwnedBackupZipPath(): string
    {
        $path = tempnam(Craft::$app->getPath()->getTempPath(), 'translation-backup-');
        if (!is_string($path)) {
            throw new \RuntimeException('Unable to allocate an owned backup ZIP path.');
        }

        return $path;
    }

    protected function createBackupZip(): \ZipArchive
    {
        return new \ZipArchive();
    }

    protected function openBackupZip(\ZipArchive $zip, string $path): bool
    {
        return $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true;
    }

    protected function addBackupZipMember(\ZipArchive $zip, string $name, string $contents): bool
    {
        return $zip->addFromString($name, $contents);
    }

    protected function closeBackupZip(\ZipArchive $zip): bool
    {
        return $zip->close();
    }

    /** @return array<string, string> */
    protected function readBackupDownloadManifest(BackupService $backupService, string $backupName): array
    {
        return $backupService->getDownloadFiles($backupName);
    }

    protected function prepareBackupDownloadResponse(string $path, string $filename): Response
    {
        return Craft::$app->getResponse()->sendFile($path, $filename, [
            'mimeType' => 'application/zip',
            'inline' => false,
        ]);
    }

    /** @param callable(): void $cleanup */
    protected function registerBackupDownloadShutdown(callable $cleanup): void
    {
        register_shutdown_function($cleanup);
    }

    /** @param callable(): void $cleanup */
    protected function registerBackupResponseCleanup(Response $response, callable $cleanup): void
    {
        $response->on(Response::EVENT_AFTER_SEND, static function() use ($cleanup): void {
            $cleanup();
        });
    }

    protected function removeOwnedBackupZip(string $path): void
    {
        if (is_file($path) && !unlink($path)) {
            $this->logError('Failed to remove owned backup ZIP', ['path' => $path]);
        }
    }

    protected function prepareOwnedBackupDownload(
        BackupService $backupService,
        string $backupName,
        string $downloadFilename,
    ): Response {
        $zipPath = $this->createOwnedBackupZipPath();
        $cleanup = fn() => $this->removeOwnedBackupZip($zipPath);
        $zip = null;
        $zipOpen = false;

        try {
            $this->registerBackupDownloadShutdown($cleanup);

            $files = $this->readBackupDownloadManifest($backupService, $backupName);
            if ($files === []) {
                throw new NotFoundHttpException(Craft::t('translation-manager', 'Backup not found'));
            }

            $zip = $this->createBackupZip();
            if (!$this->openBackupZip($zip, $zipPath)) {
                throw new \RuntimeException('Cannot create zip file');
            }
            $zipOpen = true;

            foreach ($files as $filename => $content) {
                if (!$this->addBackupZipMember($zip, $filename, $content)) {
                    throw new \RuntimeException("Cannot add backup file to zip archive: {$filename}");
                }
            }

            if (!$this->closeBackupZip($zip)) {
                throw new \RuntimeException('Cannot finalize zip file');
            }
            $zipOpen = false;

            $response = $this->prepareBackupDownloadResponse($zipPath, $downloadFilename);
            $this->registerBackupResponseCleanup($response, $cleanup);

            return $response;
        } catch (Throwable $e) {
            if ($zipOpen && $zip instanceof \ZipArchive) {
                try {
                    $this->closeBackupZip($zip);
                } catch (Throwable $closeError) {
                    $this->logError('Failed to close backup ZIP after download failure', [
                        'error' => $closeError->getMessage(),
                    ]);
                }
            }
            $cleanup();
            throw $e;
        }
    }

    /**
     * Format backup reason for display
     */
    private function _formatBackupType(string $reason): array
    {
        if (str_starts_with($reason, 'before_cleanup')) {
            return [
                'label' => Craft::t('translation-manager', 'Clean'),
                'value' => 'clean',
            ];
        }

        if (str_starts_with($reason, 'before_delete')) {
            return [
                'label' => Craft::t('translation-manager', 'Delete'),
                'value' => 'delete',
            ];
        }

        return match ($reason) {
            'import', 'before_import', 'before_php_import' => [
                'label' => Craft::t('translation-manager', 'Import'),
                'value' => 'import',
            ],
            'restore', 'before_restore' => [
                'label' => Craft::t('translation-manager', 'Restore'),
                'value' => 'restore',
            ],
            'maintenance' => [
                'label' => Craft::t('translation-manager', 'Maintenance'),
                'value' => 'maintenance',
            ],
            'scheduled' => [
                'label' => Craft::t('translation-manager', 'Scheduled'),
                'value' => 'scheduled',
            ],
            'manual', 'console' => [
                'label' => Craft::t('translation-manager', 'Manual'),
                'value' => 'manual',
            ],
            default => [
                'label' => Craft::t('translation-manager', 'Other'),
                'value' => 'other',
            ],
        };
    }
}
