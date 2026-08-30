<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use craft\services\Config;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins removal of the never-functional auto-save delay setting.
 *
 * @since 5.35.0
 */
final class AutoSaveDelayRemovalTest extends TestCase
{
    public function testShippedConfigDoesNotAdvertiseAutoSaveDelay(): void
    {
        $config = require dirname(__DIR__, 2) . '/src/config.php';

        self::assertIsArray($config);
        self::assertArrayHasKey('*', $config);
        self::assertIsArray($config['*']);
        self::assertArrayNotHasKey('autoSaveDelay', $config['*']);
    }

    public function testSettingsDoesNotRecognizeAutoSaveDelay(): void
    {
        $settings = new Settings();
        $integerFields = new \ReflectionMethod($settings, 'integerFields');

        self::assertFalse(property_exists($settings, 'autoSaveDelay'));
        self::assertFalse($settings->canSetProperty('autoSaveDelay'));
        self::assertArrayNotHasKey('autoSaveDelay', $settings->attributeLabels());
        self::assertNotContains('autoSaveDelay', $integerFields->invoke(null));
    }

    public function testLegacyConfigBootsSafelyAndIsIgnored(): void
    {
        $settings = new Settings();
        $before = $settings->getAttributes();
        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'translation-manager'
                ? ['autoSaveDelay' => 7]
                : [],
        );
        Craft::$app->set('config', $config);

        PluginHelper::applyConfigOverridesToSettings($settings, 'translation-manager');

        self::assertFalse(property_exists($settings, 'autoSaveDelay'));
        self::assertSame($before, $settings->getAttributes());
    }

    public function testSaveTriggersRemainUnchanged(): void
    {
        $template = (string)file_get_contents(dirname(__DIR__, 2) . '/src/templates/translations/index.twig');

        self::assertStringContainsString("textarea.addEventListener('blur'", $template);
        self::assertStringContainsString('settings.autoSaveEnabled|json_encode|raw', $template);
        self::assertStringContainsString('setTimeout(() => saveTranslation(id, this.value), 250);', $template);
        self::assertStringContainsString("e.key === 'Enter'", $template);
        self::assertStringContainsString("getElementById('save-all-btn')", $template);
        self::assertStringContainsString("e.key === 's'", $template);
    }

    public function testUnscopedSettingsSaveSucceedsWithoutPersistingChanges(): void
    {
        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            $settings = Settings::loadFromDatabase();

            self::assertTrue($settings->saveToDatabase());
        } finally {
            $transaction->rollBack();
        }
    }

    public function testFreshInstallAndPluginSchemaContractOmitAutoSaveDelay(): void
    {
        $installSource = (string)file_get_contents(dirname(__DIR__, 2) . '/src/migrations/Install.php');

        self::assertStringNotContainsString("'autoSaveDelay'", $installSource);
        self::assertSame('1.0.1', TranslationManager::getInstance()->schemaVersion);
    }
}
