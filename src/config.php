<?php
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
 *
 * @since 1.0.0
 */


return [
    // Global settings
    '*' => [
        // ========================================
        // GENERAL SETTINGS
        // ========================================
        // Basic plugin configuration

        'pluginName' => 'Translation Manager',
        'logLevel' => 'error',         // Log level: 'debug', 'info', 'warning', 'error'


        // ========================================
        // TRANSLATION SOURCES
        // ========================================
        // Configure which translation sources to capture

        // Site Translations
        'enableSiteTranslations' => true,
        'translationCategory' => 'messages', // Category for site translations (e.g., 'messages' for |t('messages'))
        'sourceLanguage' => 'en',            // Language your template strings are written in (e.g., 'Copyright', 'Submit')

        // Site Translation Skip Patterns
        // Text patterns to skip when capturing site translations (array of strings to skip)
        'skipPatterns' => [
            // 'ID',
            // 'Title',
            // 'Status',
        ],

        // Formie Integration
        'enableFormieIntegration' => true,

        // Form Exclusion Patterns
        // Forms with handles OR titles containing these patterns will be skipped entirely (case-insensitive)
        // Useful for excluding language-specific duplicates like 'booking-ar' or 'Contact Form (Ar)'
        'excludeFormHandlePatterns' => [
            // '(Ar)',     // Matches form titles like "Geely Service Survey (Ar)"
            // '(ar)',     // Matches form titles like "Contact Form (ar)"
            // 'Ar1',      // Matches handles like offersBmwX1X2Ar11, fantasypremiurleagueAr11
            // 'PricesAr', // Matches handles like offersGeelySpecialPricesAr
        ],


        // ========================================
        // FILE GENERATION
        // ========================================
        // PHP translation file generation settings

        'autoExport' => true,          // Automatically generate translation files when translations are saved
        'exportPath' => '@root/translations', // Path where PHP translation files should be generated


        // ========================================
        // BACKUP SETTINGS
        // ========================================
        // Backup configuration and retention

        'backupEnabled' => true,
        'backupSchedule' => 'manual',  // Options: 'manual', 'daily', 'weekly', 'monthly'
        'backupRetentionDays' => 30,   // Number of days to keep automatic backups (0 = keep forever)
        'backupOnImport' => true,      // Automatically create backup before importing CSV files
        'backupPath' => '@storage/translation-manager/backups',
        'backupVolumeUid' => null,     // Optional: Asset volume UID for backups


        // ========================================
        // INTERFACE SETTINGS
        // ========================================
        // Control panel interface options

        'itemsPerPage' => 100,         // Number of translations to show per page
        'showContext' => false,        // Display the translation context in the interface
        'enableSuggestions' => false,  // Enable translation suggestions (future feature)

        // Auto-save Settings
        'autoSaveEnabled' => false,    // Automatically save each translation when you click outside the field
        'autoSaveDelay' => 2,          // Delay in seconds before auto-save triggers
    ],

    // Dev environment settings
    'dev' => [
        'logLevel' => 'debug',         // More detailed logging in development
        'autoExport' => false,         // Manual export in dev
        'backupSchedule' => 'manual',  // Manual backups in dev
    ],

    // Staging environment settings
    'staging' => [
        'logLevel' => 'info',          // Moderate logging in staging
        'autoExport' => true,
        'backupSchedule' => 'weekly',
    ],

    // Production environment settings
    'production' => [
        'logLevel' => 'warning',       // Less verbose logging in production
        'autoExport' => true,
        'backupEnabled' => true,
        'backupSchedule' => 'daily',
        // 'backupVolumeUid' => 'your-volume-uid-here', // Use asset volume in production
    ],
];
