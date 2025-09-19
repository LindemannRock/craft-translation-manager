<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Debug controller for troubleshooting volume backup issues
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Debug Controller
 */
class DebugController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;

    /**
     * Debug volume backup configuration
     */
    public function actionVolumeBackups(): Response
    {
        $this->requireAcceptsJson();

        $settings = TranslationManager::getInstance()->getSettings();
        $backupService = TranslationManager::getInstance()->backup;

        $debug = [
            'backupVolumeUid' => $settings->backupVolumeUid,
            'useVolume' => false,
            'volumeFs' => null,
            'volumePath' => null,
            'backups' => [],
            'errors' => []
        ];

        try {
            if ($settings->backupVolumeUid) {
                $volume = Craft::$app->getVolumes()->getVolumeByUid($settings->backupVolumeUid);
                if ($volume) {
                    $debug['volumeName'] = $volume->name;
                    $debug['volumeHandle'] = $volume->handle;

                    $fs = $volume->getFs();
                    if ($fs) {
                        $debug['useVolume'] = true;
                        $debug['volumeFs'] = get_class($fs);
                        $debug['volumePath'] = 'translation-manager/backups';

                        // Try to list base directory
                        try {
                            $debug['baseDirectoryExists'] = $fs->directoryExists('translation-manager/backups');
                        } catch (\Exception $e) {
                            $debug['baseDirectoryError'] = $e->getMessage();
                        }

                        // Try to list subdirectories
                        $subfolders = ['manual', 'scheduled', 'imports', 'maintenance', 'other'];
                        foreach ($subfolders as $subfolder) {
                            $folderPath = 'translation-manager/backups/' . $subfolder;
                            try {
                                $exists = $fs->directoryExists($folderPath);
                                $debug['subfolders'][$subfolder] = ['exists' => $exists];

                                if ($exists) {
                                    $contents = $fs->listContents($folderPath, false);
                                    $debug['subfolders'][$subfolder]['contents'] = $contents;
                                }
                            } catch (\Exception $e) {
                                $debug['subfolders'][$subfolder]['error'] = $e->getMessage();
                            }
                        }
                    }
                } else {
                    $debug['errors'][] = 'Volume not found';
                }
            } else {
                $debug['errors'][] = 'No volume UID configured';
            }

            // Try getting backups through service
            try {
                $debug['backups'] = $backupService->getBackups();
            } catch (\Exception $e) {
                $debug['errors'][] = 'Backup service error: ' . $e->getMessage();
            }

        } catch (\Exception $e) {
            $debug['errors'][] = 'General error: ' . $e->getMessage();
        }

        return $this->asJson($debug);
    }
}