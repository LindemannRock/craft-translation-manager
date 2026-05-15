<?php

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins language fan-out to canonical locale mapping so mapped-source locales
 * do not keep creating duplicate DB rows during scan/import/capture.
 *
 * @since 5.24.0
 */
final class TranslationManagerUniqueLanguagesTest extends TestCase
{
    public function testUniqueLanguagesApplyActiveLocaleMapping(): void
    {
        $settings = Settings::loadFromDatabase();
        $originalMapping = $settings->localeMapping;

        try {
            $settings->localeMapping = [
                ['source' => 'en-US', 'destination' => 'en', 'enabled' => true],
            ];
            self::assertTrue($settings->saveToDatabase(['localeMapping']));
            TranslationManager::getInstance()->setSettings([]);

            $languages = TranslationManager::getInstance()->getUniqueLanguages();

            self::assertContains('en', $languages);
            self::assertNotContains('en-US', $languages);
        } finally {
            $settings = Settings::loadFromDatabase();
            $settings->localeMapping = $originalMapping;
            $settings->saveToDatabase(['localeMapping']);
            TranslationManager::getInstance()->setSettings([]);
        }
    }

    public function testUniqueLanguagesIncludeRawLocaleWhenMappingIsDisabled(): void
    {
        $this->requireSiteLanguage('en-US');

        $settings = Settings::loadFromDatabase();
        $originalMapping = $settings->localeMapping;

        try {
            $settings->localeMapping = [
                ['source' => 'en-US', 'destination' => 'en', 'enabled' => false],
            ];
            self::assertTrue($settings->saveToDatabase(['localeMapping']));
            TranslationManager::getInstance()->setSettings([]);

            $languages = TranslationManager::getInstance()->getUniqueLanguages();

            self::assertContains('en-US', $languages);
        } finally {
            $settings = Settings::loadFromDatabase();
            $settings->localeMapping = $originalMapping;
            $settings->saveToDatabase(['localeMapping']);
            TranslationManager::getInstance()->setSettings([]);
        }
    }

    private function requireSiteLanguage(string $language): void
    {
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if ($site->language === $language) {
                return;
            }
        }

        self::markTestSkipped("Test requires a {$language} site.");
    }
}
