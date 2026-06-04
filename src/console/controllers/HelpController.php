<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\console\controllers;

use lindemannrock\base\console\controllers\AbstractHelpController;

/**
 * Console help for Translation Manager commands.
 *
 * @since 5.25.0
 */
final class HelpController extends AbstractHelpController
{
    /**
     * @inheritdoc
     */
    protected function helpManifest(): array
    {
        return [
            'title' => 'Translation Manager',
            'pluginHandle' => 'translation-manager',
            'commandPrefix' => 'ddev craft',
            'summary' => 'Use these commands to capture translations, generate PHP files, import existing translation files, clean unused rows, manage backups, and test AI providers.',
            'common' => [
                'translations/import',
                'translations/generate-all',
                'maintenance/clean-by-type',
                'backup/create',
                'debug/test-ai',
            ],
            'groups' => [
                [
                    'name' => 'translations',
                    'label' => 'Translations',
                    'description' => 'Capture, import, generate, and AI-draft translations.',
                    'commands' => [
                        [
                            'path' => 'translations/capture-formie',
                            'summary' => 'Capture translatable text from Formie forms.',
                            'description' => 'Scan Formie forms and add their translatable strings to Translation Manager.',
                            'examples' => [
                                'translation-manager/translations/capture-formie',
                            ],
                        ],
                        [
                            'path' => 'translations/generate-formie',
                            'summary' => 'Generate Formie translation files.',
                            'description' => 'Write translated Formie strings from the database to PHP translation files.',
                            'examples' => [
                                'translation-manager/translations/generate-formie',
                            ],
                        ],
                        [
                            'path' => 'translations/generate-site',
                            'summary' => 'Generate site translation files.',
                            'description' => 'Write translated site strings from the database to PHP translation files.',
                            'examples' => [
                                'translation-manager/translations/generate-site',
                            ],
                        ],
                        [
                            'path' => 'translations/generate-all',
                            'summary' => 'Generate Formie and site translation files.',
                            'description' => 'Run both Formie and site translation file generation in one command.',
                            'examples' => [
                                'translation-manager/translations/generate-all',
                            ],
                        ],
                        [
                            'path' => 'translations/import',
                            'summary' => 'Import PHP translation files from disk.',
                            'description' => 'Import generated or existing PHP translation files into the database. With no scope, this command prints a preview and writes nothing.',
                            'usageOptions' => '[--all] [--language=<language>] [--category=<category>]',
                            'options' => [
                                [
                                    'name' => '--all',
                                    'description' => 'Import every discovered PHP translation file instead of only showing the preview.',
                                ],
                                [
                                    'name' => '--language',
                                    'description' => 'Import only one language directory, such as ar or de.',
                                ],
                                [
                                    'name' => '--category',
                                    'description' => 'Import only one category file, such as site or formie.',
                                ],
                            ],
                            'examples' => [
                                'translation-manager/translations/import',
                                'translation-manager/translations/import --all',
                                'translation-manager/translations/import --language=ar --category=formie',
                            ],
                        ],
                        [
                            'path' => 'translations/ai-draft',
                            'summary' => 'Create AI draft translations for one language.',
                            'description' => 'Send pending rows for one target language to the configured AI provider. The generated rows remain drafts for human review.',
                            'arguments' => '<language>',
                            'usageOptions' => '[--limit=<count>] [--type=<all|forms|site>] [--provider=<provider>]',
                            'options' => [
                                [
                                    'name' => '--limit',
                                    'description' => 'Maximum pending rows to process. Default: 50.',
                                ],
                                [
                                    'name' => '--type',
                                    'description' => 'Filter rows by all, forms, or site. Default: all.',
                                ],
                                [
                                    'name' => '--provider',
                                    'description' => 'AI provider handle. Omit to use the plugin setting.',
                                ],
                            ],
                            'examples' => [
                                'translation-manager/translations/ai-draft ar',
                                'translation-manager/translations/ai-draft de --limit=100 --type=site --provider=mock',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'maintenance',
                    'label' => 'Maintenance',
                    'description' => 'Scan templates and clean unused translations.',
                    'commands' => [
                        [
                            'path' => 'maintenance/scan-templates',
                            'summary' => 'Scan templates and mark unused site translations.',
                            'description' => 'Scan Craft templates for translation key usage and update unused/reactivated status.',
                            'examples' => [
                                'translation-manager/maintenance/scan-templates',
                            ],
                        ],
                        [
                            'path' => 'maintenance/preview-scan',
                            'summary' => 'Preview a template scan without writing changes.',
                            'description' => 'Show which template keys would be found and which site translation rows would be marked unused.',
                            'examples' => [
                                'translation-manager/maintenance/preview-scan',
                            ],
                        ],
                        [
                            'path' => 'maintenance/clean-unused',
                            'summary' => 'Delete all rows already marked unused.',
                            'description' => 'Delete unused translation rows. Use the Control Panel if you want to review rows before deleting them.',
                            'examples' => [
                                'translation-manager/maintenance/clean-unused',
                            ],
                        ],
                        [
                            'path' => 'maintenance/clean-by-type',
                            'summary' => 'Delete unused rows by type.',
                            'description' => 'Delete unused rows for all translations, site translations, or form translations. Provider filtering is only valid for form translations.',
                            'usageOptions' => '--type=<all|site|forms> [--provider=<formie|freeform>]',
                            'options' => [
                                [
                                    'name' => '--type',
                                    'description' => 'all, site, or forms.',
                                    'required' => true,
                                ],
                                [
                                    'name' => '--provider',
                                    'description' => 'Only with --type=forms. Use formie or freeform.',
                                ],
                            ],
                            'examples' => [
                                'translation-manager/maintenance/clean-by-type --type=all',
                                'translation-manager/maintenance/clean-by-type --type=forms',
                                'translation-manager/maintenance/clean-by-type --type=forms --provider=formie',
                            ],
                            'notes' => [
                                'This command is maintenance/clean-by-type, not translations/clean-by-type.',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'backup',
                    'label' => 'Backups',
                    'description' => 'Create, list, clean, and run scheduled backups.',
                    'commands' => [
                        [
                            'path' => 'backup/create',
                            'summary' => 'Create a translation backup.',
                            'description' => 'Create a backup of all Translation Manager rows when backups are enabled.',
                            'usageOptions' => '[--reason=<reason>] [--clean=0]',
                            'options' => [
                                [
                                    'name' => '--reason',
                                    'description' => 'Short reason stored with the backup. Default: console.',
                                ],
                                [
                                    'name' => '--clean',
                                    'description' => 'Set to 0 to skip old-backup cleanup after creating the backup.',
                                ],
                            ],
                            'examples' => [
                                'translation-manager/backup/create',
                                'translation-manager/backup/create --reason="Before import"',
                            ],
                        ],
                        [
                            'path' => 'backup/scheduled',
                            'summary' => 'Run the scheduled-backup check now.',
                            'description' => 'Check backup settings and create a scheduled backup when the configured schedule is due.',
                            'examples' => [
                                'translation-manager/backup/scheduled',
                            ],
                        ],
                        [
                            'path' => 'backup/list',
                            'summary' => 'List available backups.',
                            'description' => 'Show the backup date, reason, size, and translation count for each available backup.',
                            'examples' => [
                                'translation-manager/backup/list',
                            ],
                        ],
                        [
                            'path' => 'backup/clean',
                            'summary' => 'Delete old backups using retention settings.',
                            'description' => 'Clean old backups according to the configured backup retention days.',
                            'examples' => [
                                'translation-manager/backup/clean',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'debug',
                    'label' => 'Debug',
                    'description' => 'Test AI provider configuration.',
                    'commands' => [
                        [
                            'path' => 'debug/test-ai',
                            'summary' => 'Test the configured AI provider.',
                            'description' => 'Make a live provider test call, then translate a short sample text. Use this before running AI draft batches.',
                            'usageOptions' => '[--provider=<provider>] [--target-language=<language>] [--text=<text>]',
                            'options' => [
                                [
                                    'name' => '--provider',
                                    'description' => 'Provider handle to test. Omit to use the plugin setting.',
                                ],
                                [
                                    'name' => '--target-language',
                                    'description' => 'Target language for the sample translation. Default: de.',
                                ],
                                [
                                    'name' => '--text',
                                    'description' => 'Sample text to translate.',
                                ],
                            ],
                            'examples' => [
                                'translation-manager/debug/test-ai',
                                'translation-manager/debug/test-ai --provider=openai',
                                'translation-manager/debug/test-ai --provider=gemini --target-language=ar --text="Welcome to our agency"',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
