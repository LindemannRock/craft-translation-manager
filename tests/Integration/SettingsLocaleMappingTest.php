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
 * Pins the locale-mapping contract on `Settings::mapLanguage()`: an enabled
 * mapping rewrites the source locale to its destination, a disabled mapping
 * is ignored, and any locale not covered by the mapping list passes through
 * unchanged.
 *
 * Locale canonicalization is the cross-plugin consistency rule that gates
 * import, export, and runtime capture — getting it wrong silently scatters
 * translations across regional variants (e.g. `en-US` rows orphaned from `en`).
 *
 * Constructs a fresh `Settings` instance with a known `$localeMapping` array
 * so the test does not depend on whatever mapping happens to live in the
 * DB-persisted plugin settings.
 *
 * @since 5.24.0
 */
final class SettingsLocaleMappingTest extends TestCase
{
    public function testEnabledMappingCanonicalizes(): void
    {
        $settings = new Settings();
        $settings->localeMapping = [
            ['source' => 'en-US', 'destination' => 'en', 'enabled' => true],
            ['source' => 'nl-BE', 'destination' => 'nl-NL', 'enabled' => true],
        ];

        self::assertSame('en', $settings->mapLanguage('en-US'));
        self::assertSame('nl-NL', $settings->mapLanguage('nl-BE'));
    }

    public function testDisabledMappingIsIgnored(): void
    {
        $settings = new Settings();
        $settings->localeMapping = [
            ['source' => 'en-US', 'destination' => 'en', 'enabled' => false],
        ];

        self::assertSame(
            'en-US',
            $settings->mapLanguage('en-US'),
            'A disabled mapping must not rewrite the locale.',
        );
    }

    public function testUnmappedLocalePassesThrough(): void
    {
        $settings = new Settings();
        $settings->localeMapping = [
            ['source' => 'en-US', 'destination' => 'en', 'enabled' => true],
        ];

        self::assertSame(
            'fr-CA',
            $settings->mapLanguage('fr-CA'),
            'A locale not present in the mapping list must pass through unchanged.',
        );
    }

    public function testIncompleteMappingEntryIsSkipped(): void
    {
        $settings = new Settings();
        $settings->localeMapping = [
            ['source' => '', 'destination' => 'en', 'enabled' => true],
            ['source' => 'en-US', 'destination' => '', 'enabled' => true],
            ['source' => 'en-GB', 'destination' => 'en', 'enabled' => true],
        ];

        self::assertSame('en-US', $settings->mapLanguage('en-US'));
        self::assertSame('en', $settings->mapLanguage('en-GB'));
    }
}
