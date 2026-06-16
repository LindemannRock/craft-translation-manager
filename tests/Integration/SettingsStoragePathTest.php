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
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * Pins Translation Manager's generated-file and backup path policies.
 *
 * @since 5.25.0
 */
final class SettingsStoragePathTest extends TestCase
{
    private const ENV_NAME = 'LR_TRANSLATION_PATH_TEST';

    protected function tearDown(): void
    {
        putenv(self::ENV_NAME);
        unset($_ENV[self::ENV_NAME], $_SERVER[self::ENV_NAME]);

        parent::tearDown();
    }

    public function testTranslationsAliasGenerationPathPasses(): void
    {
        $settings = $this->settingsForGenerationPath('@translations');

        self::assertTrue($settings->validate(['generationPath']), json_encode($settings->getErrors()));
    }

    public function testEnvGenerationPathResolvingToTranslationsAliasPasses(): void
    {
        $this->setEnvValue('@translations');
        $settings = $this->settingsForGenerationPath('$' . self::ENV_NAME);

        self::assertTrue($settings->validate(['generationPath']), json_encode($settings->getErrors()));
        $expected = realpath(Craft::getAlias('@translations')) ?: Craft::getAlias('@translations');
        self::assertSame($expected, $settings->getGenerationPath());
    }

    public function testEnvGenerationPathResolvingInsideTranslationsPasses(): void
    {
        $path = Craft::getAlias('@translations');
        $this->setEnvValue($path);
        $settings = $this->settingsForGenerationPath('$' . self::ENV_NAME);

        self::assertTrue($settings->validate(['generationPath']), json_encode($settings->getErrors()));
        $expected = realpath($path) ?: $path;
        self::assertSame($expected, $settings->getGenerationPath());
    }

    public function testRootGenerationPathFails(): void
    {
        $settings = $this->settingsForGenerationPath('@root/translations');

        self::assertFalse($settings->validate(['generationPath']));
        self::assertArrayHasKey('generationPath', $settings->getErrors());
        self::assertSame(Craft::getAlias('@translations'), $settings->getGenerationPath());
    }

    public function testTranslationsSubfolderGenerationPathFails(): void
    {
        $settings = $this->settingsForGenerationPath('@translations/translation-manager');

        self::assertFalse($settings->validate(['generationPath']));
        self::assertArrayHasKey('generationPath', $settings->getErrors());
        self::assertSame(Craft::getAlias('@translations'), $settings->getGenerationPath());
    }

    public function testStorageGenerationPathFails(): void
    {
        $settings = $this->settingsForGenerationPath('@storage/translation-manager/translations');

        self::assertFalse($settings->validate(['generationPath']));
        self::assertArrayHasKey('generationPath', $settings->getErrors());
        self::assertSame(Craft::getAlias('@translations'), $settings->getGenerationPath());
    }

    public function testInvalidGenerationAliasFailsValidationWithoutBreakingResolvedPathFallback(): void
    {
        $settings = $this->settingsForGenerationPath('@translationz');

        self::assertFalse($settings->validate(['generationPath']));
        self::assertArrayHasKey('generationPath', $settings->getErrors());
        self::assertSame(Craft::getAlias('@translations'), $settings->getGenerationPath());
    }

    public function testEnvBackupPathResolvingInsideStoragePasses(): void
    {
        $path = Craft::getAlias('@storage/translation-manager/backups');
        $this->setEnvValue($path);
        $settings = $this->settingsForBackupPath('$' . self::ENV_NAME);

        self::assertTrue($settings->validate(['backupPath']), json_encode($settings->getErrors()));
        self::assertSame($path, $settings->getBackupPath());
    }

    public function testInvalidBackupAliasFailsValidationWithoutBreakingResolvedPathFallback(): void
    {
        $settings = $this->settingsForBackupPath('@storages/translation-manager/backups');

        self::assertFalse($settings->validate(['backupPath']));
        self::assertArrayHasKey('backupPath', $settings->getErrors());
        self::assertSame(Craft::getAlias('@storage/translation-manager/backups'), $settings->getBackupPath());
    }

    private function settingsForGenerationPath(string $path): Settings
    {
        $settings = new Settings();
        $settings->generationPath = $path;

        return $settings;
    }

    private function settingsForBackupPath(string $path): Settings
    {
        $settings = new Settings();
        $settings->backupPath = $path;

        return $settings;
    }

    private function setEnvValue(string $value): void
    {
        putenv(self::ENV_NAME . '=' . $value);
        $_ENV[self::ENV_NAME] = $value;
        $_SERVER[self::ENV_NAME] = $value;
    }
}
