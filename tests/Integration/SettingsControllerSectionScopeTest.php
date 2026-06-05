<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\controllers\SettingsController;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.25.0
 */
#[CoversClass(SettingsController::class)]
final class SettingsControllerSectionScopeTest extends TestCase
{
    public function testSettingsSectionsMatchRenderedFormScopes(): void
    {
        $controller = new SettingsController('settings', TranslationManager::$plugin);
        $method = new \ReflectionMethod($controller, '_validationAttributesForSection');

        $expected = [
            'general' => [
                'pluginName',
                'requireApproval',
                'logLevel',
            ],
            'generation' => [
                'autoGenerate',
                'generationPath',
            ],
            'backup' => [
                'backupEnabled',
                'backupOnImport',
                'backupSchedule',
                'backupRetentionDays',
                'backupVolumeUid',
                'backupPath',
            ],
            'sources' => [
                'enableSiteTranslations',
                'translationCategories',
                'sourceLanguage',
                'skipPatterns',
            ],
            'interface' => [
                'itemsPerPage',
                'autoSaveEnabled',
                'timeFormat',
                'monthFormat',
                'dateOrder',
                'dateSeparator',
                'showSeconds',
                'exportsCsv',
                'exportsJson',
                'exportsExcel',
            ],
            'locale-mapping' => [
                'localeMapping',
            ],
            'integrations' => [
                'enableFormieIntegration',
                'excludeFormHandlePatterns',
            ],
            'ai' => [
                'enableAiTranslations',
                'aiProvider',
                'openAiApiKey',
                'openAiModel',
                'geminiApiKey',
                'geminiModel',
                'anthropicApiKey',
                'anthropicModel',
            ],
            'capture' => [
                'captureMissingTranslations',
                'captureMissingOnlyDevMode',
            ],
        ];

        foreach ($expected as $section => $attributes) {
            self::assertSame($attributes, $method->invoke($controller, $section), "Unexpected {$section} settings scope.");
        }
    }
}
