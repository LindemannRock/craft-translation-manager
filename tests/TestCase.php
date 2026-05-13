<?php

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests;

use Craft;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Base test case for translation-manager integration tests.
 *
 * Extends the shared {@see IntegrationTestCase} for component snapshot/restore
 * and generic Query helpers, and layers plugin-specific shorthand on top:
 *  - direct accessor for the translations service
 *  - per-test purge of marker-prefixed rows in `translationmanager_translations`
 *
 * Source-language test markers must use only Latin letters/digits/underscores —
 * `purgeRowsByMarker()` does not escape SQL LIKE metacharacters in the prefix,
 * and `TranslationsService::createOrUpdateTranslation()` rejects strings whose
 * script doesn't match the configured `sourceLanguage` (typically `en` here).
 *
 * @since 5.24.0
 */
abstract class TestCase extends IntegrationTestCase
{
    /**
     * Marker prefix prepended to every test-seeded source string. Cleanup
     * targets this prefix in setUp + tearDown so CP-created rows are
     * untouched.
     */
    protected const MARKER = '__tm_test_';

    protected TranslationsService $translations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translations = TranslationManager::getInstance()->translations;
        $this->purgeMarkerRows();
    }

    protected function tearDown(): void
    {
        $this->purgeMarkerRows();
        parent::tearDown();
    }

    /**
     * Wipe any rows in `translationmanager_translations` whose `source` text
     * starts with the test marker. Runs in setUp (defensive, in case a prior
     * crashed test left rows behind) and tearDown (the normal cleanup path).
     */
    protected function purgeMarkerRows(): void
    {
        $this->purgeRowsByMarker(
            '{{%translationmanager_translations}}',
            'source',
            self::MARKER,
        );
    }

    /**
     * Fetch all rows for a given marker source string. Returns rows sorted by
     * language so callers can index into a stable order across multi-language
     * fan-out (one row per unique site language).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function fetchRowsForSource(string $source): array
    {
        return (new \craft\db\Query())
            ->from('{{%translationmanager_translations}}')
            ->where(['source' => $source])
            ->orderBy(['language' => SORT_ASC])
            ->all();
    }

    /**
     * Sanity-check: are we operating against the expected source language?
     * The Twig-skip and happy-path tests assume Latin script; bail loudly
     * if the install is configured otherwise.
     */
    protected function requireLatinSourceLanguage(): void
    {
        $sourceLanguage = TranslationManager::getInstance()->getSettings()->sourceLanguage;
        if (!str_starts_with($sourceLanguage, 'en')) {
            self::markTestSkipped(
                "Test requires a Latin source language; found '{$sourceLanguage}'.",
            );
        }
    }

    /**
     * Defensive: tests assume at least one site is configured (real CP installs
     * always have one — DDEV scaffolding does too).
     */
    protected function requireAtLeastOneSite(): void
    {
        if (count(Craft::$app->getSites()->getAllSites()) === 0) {
            self::markTestSkipped('Test requires at least one configured site.');
        }
    }
}
