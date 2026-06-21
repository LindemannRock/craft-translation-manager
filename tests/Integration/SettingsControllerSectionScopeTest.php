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
use lindemannrock\translationmanager\services\AiTranslationService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\web\NotFoundHttpException;

/**
 * @since 5.25.0
 */
#[CoversClass(SettingsController::class)]
#[CoversClass(AiTranslationService::class)]
final class SettingsControllerSectionScopeTest extends TestCase
{
    private mixed $serverAiFlag = null;
    private mixed $envAiFlag = null;

    protected function setUp(): void
    {
        $this->serverAiFlag = $_SERVER['TRANSLATION_MANAGER_ENABLE_AI'] ?? null;
        $this->envAiFlag = $_ENV['TRANSLATION_MANAGER_ENABLE_AI'] ?? null;
        unset($_SERVER['TRANSLATION_MANAGER_ENABLE_AI'], $_ENV['TRANSLATION_MANAGER_ENABLE_AI']);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->serverAiFlag === null) {
            unset($_SERVER['TRANSLATION_MANAGER_ENABLE_AI']);
        } else {
            $_SERVER['TRANSLATION_MANAGER_ENABLE_AI'] = $this->serverAiFlag;
        }

        if ($this->envAiFlag === null) {
            unset($_ENV['TRANSLATION_MANAGER_ENABLE_AI']);
        } else {
            $_ENV['TRANSLATION_MANAGER_ENABLE_AI'] = $this->envAiFlag;
        }

        parent::tearDown();
    }

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
                'runtimeTranslationSource',
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
                'enableFreeformIntegration',
                'excludeFormHandlePatterns',
            ],
            'ai' => [],
            'capture' => [
                'captureMissingTranslations',
                'captureMissingOnlyDevMode',
            ],
        ];

        foreach ($expected as $section => $attributes) {
            self::assertSame($attributes, $method->invoke($controller, $section), "Unexpected {$section} settings scope.");
        }
    }

    public function testAiSectionFallsBackWhenExperimentalGateIsDisabled(): void
    {
        $controller = new SettingsController('settings', TranslationManager::$plugin);
        $method = new \ReflectionMethod($controller, '_validSettingsSection');

        self::assertSame('general', $method->invoke($controller, 'ai'));
    }

    public function testAiServiceIsUnavailableWhenExperimentalGateIsDisabled(): void
    {
        $this->expectException(NotFoundHttpException::class);

        TranslationManager::getInstance()->get('ai')->getAvailableProviders();
    }
}
