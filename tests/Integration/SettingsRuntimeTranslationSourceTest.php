<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.29.0
 */
final class SettingsRuntimeTranslationSourceTest extends TestCase
{
    public function testRuntimeSourceOptionsUseCurrentStoredValues(): void
    {
        $settings = new Settings();

        self::assertSame(
            ['php-files', 'database', 'hybrid'],
            array_column($settings->getRuntimeTranslationSourceOptions(), 'value'),
        );
    }

    public function testLegacyRuntimeSourceValuesNormalizeBeforeValidation(): void
    {
        $settings = new Settings();
        $settings->runtimeTranslationSource = 'generated-files';

        self::assertTrue($settings->validate(['runtimeTranslationSource']));
        self::assertSame(Settings::RUNTIME_SOURCE_PHP_FILES, $settings->runtimeTranslationSource);

        $settings->runtimeTranslationSource = 'database-with-php-fallback';

        self::assertTrue($settings->validate(['runtimeTranslationSource']));
        self::assertSame(Settings::RUNTIME_SOURCE_HYBRID, $settings->runtimeTranslationSource);
    }

    public function testInvalidRuntimeSourceIsRejected(): void
    {
        $settings = new Settings();
        $settings->runtimeTranslationSource = 'generated-files-with-db';

        self::assertFalse($settings->validate(['runtimeTranslationSource']));
        self::assertArrayHasKey('runtimeTranslationSource', $settings->getErrors());
    }
}
