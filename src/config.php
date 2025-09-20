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
    'translationCategory' => 'messages',

    // Enable/disable Formie integration
    'enableFormieIntegration' => true,

    // Enable/disable site translation integration
    'enableSiteTranslations' => true,

    // Auto-save settings
    'autoSaveEnabled' => false,
    'autoSaveDelay' => 2,

    // Interface settings
    'itemsPerPage' => 100,
    'showContext' => false,
    'enableSuggestions' => false,

    // Export settings
    'autoExport' => true,
    'exportPath' => '@root/translations',

    // Backup settings
    'backupEnabled' => true,
    'backupSchedule' => 'manual', // Options: 'manual', 'daily', 'weekly', 'monthly'
    'backupRetentionDays' => 30,
    'backupOnImport' => true,
    'backupPath' => '@storage/translation-manager/backups',
    'backupVolumeUid' => null, // Optional: Set a specific asset volume UID for backups

    // Site translation skip patterns (array of strings to skip)
    'skipPatterns' => [
        // 'ID',
        // 'Title',
        // 'Status',
    ],

    // Logging settings
    'logLevel' => 'error', // Options: 'trace', 'info', 'warning', 'error'

    // Multi-environment example
    // '*' => [
    //     'translationCategory' => 'messages',
    //     'logLevel' => 'error',
    // ],
    // 'dev' => [
    //     'autoExport' => false,
    //     'logLevel' => 'trace', // More detailed logging in development
    //     'backupSchedule' => 'manual',
    // ],
    // 'production' => [
    //     'autoExport' => true,
    //     'backupEnabled' => true,
    //     'backupSchedule' => 'daily',
    //     'logLevel' => 'warning',
    //     'backupVolumeUid' => 'your-volume-uid-here', // Use asset volume in production
    // ],
];