<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Configuration file template
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

/**
 * Translation Manager config.php
 *
 * This file exists only as a template for the Translation Manager settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'translation-manager.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    // The default translation category for site translations
    'translationCategory' => 'site',

    // Enable/disable Formie integration
    'enableFormieIntegration' => true,

    // Enable/disable site translation integration
    'enableSiteIntegration' => true,

    // Auto-save settings
    'autoSave' => false,
    'autoSaveDelay' => 500,

    // Pagination
    'itemsPerPage' => 100,

    // Export settings
    'autoExport' => true,
    'exportPath' => '@root/translations',
    'generatedFileHeader' => 'Auto-generated: {date}',

    // Backup settings
    'backupEnabled' => true,
    'backupSchedule' => 'daily',
    'backupRetention' => 30,
    'backupBeforeImport' => true,
    'backupBeforeRestore' => true,
    'backupPath' => '@storage/backups/translation-manager',

    // Import settings
    'importSizeLimit' => 5000,
    'importBatchSize' => 50,

    // Deduplication settings
    'enableDeduplication' => true,

    // Multi-environment example
    // '*' => [
    //     'translationCategory' => 'site',
    // ],
    // 'dev' => [
    //     'autoExport' => false,
    // ],
    // 'production' => [
    //     'autoExport' => true,
    //     'backupEnabled' => true,
    // ],
];