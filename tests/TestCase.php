<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests;

use Craft;
use craft\queue\BaseJob;
use craft\queue\Queue;
use lindemannrock\base\testing\IntegrationTestCase;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\services\ScheduledBackupScheduler;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\Support\IsolatedQueue;
use lindemannrock\translationmanager\TranslationManager;
use Throwable;

/**
 * Base test case for translation-manager integration tests.
 *
 * Extends the shared {@see IntegrationTestCase} for component snapshot/restore
 * and generic Query helpers, and layers plugin-specific shorthand on top:
 *  - direct accessor for the translations service
 *  - per-test purge of marker-prefixed rows in `translationmanager_translations`
 *  - an isolated queue component backed by the bootstrap-created temporary table
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
    private static ?self $activeTest = null;

    /**
     * Marker prefix prepended to every test-seeded source string. Cleanup
     * targets this prefix in setUp + tearDown so CP-created rows are
     * untouched.
     */
    protected const MARKER = '__tm_test_';

    protected TranslationsService $translations;
    protected ScheduledBackupScheduler $scheduledBackups;

    /** @var array<string, mixed>|null */
    private ?array $settingsSnapshot = null;
    /** @var array<string, object> */
    private array $appComponentSnapshots = [];
    private ?object $originalQueue = null;
    private bool $isolationFinished = false;
    private bool $baseStateInitialised = false;

    protected function setUp(): void
    {
        self::$activeTest = $this;
        $this->isolationFinished = false;

        try {
            parent::setUp();
            $this->baseStateInitialised = true;
            $this->snapshotAppComponents();
            $this->settingsSnapshot = TranslationManager::getInstance()->getSettings()->getAttributes();
            $this->isolateQueue();
            $this->translations = TranslationManager::getInstance()->translations;
            $this->scheduledBackups = TranslationManager::getInstance()->scheduledBackups;
            $this->purgeMarkerRows();
        } catch (Throwable $exception) {
            try {
                $this->finishIsolation();
            } catch (Throwable $cleanupException) {
                fwrite(STDERR, 'Translation Manager setup cleanup failed: ' . $cleanupException->getMessage() . PHP_EOL);
            }
            throw $exception;
        }
    }

    protected function tearDown(): void
    {
        $this->finishIsolation();
    }

    /**
     * Runner fallback when child teardown exits before parent cleanup.
     *
     * @since 5.35.0
     */
    public static function finishActiveTestIsolation(): void
    {
        self::$activeTest?->finishIsolation();
    }

    /** Replace a plugin component with exact automatic restoration. */
    protected function replacePluginComponent(string $id, object $component): void
    {
        $this->swapPluginComponent('translation-manager', $id, $component);
    }

    /** Push one job into the connection-local shadow queue. */
    protected function pushOwnedJob(BaseJob $job, int $delay = 0): int
    {
        return (int)Craft::$app->getQueue()->delay($delay)->push($job);
    }

    protected function settings(): Settings
    {
        return TranslationManager::getInstance()->getSettings();
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

    private function snapshotAppComponents(): void
    {
        foreach (['config', 'mutex', 'request', 'response', 'volumes'] as $id) {
            if (Craft::$app->has($id)) {
                $component = Craft::$app->get($id);
                if (is_object($component)) {
                    $this->appComponentSnapshots[$id] = $component;
                }
            }
        }
    }

    private function isolateQueue(): void
    {
        $queue = Craft::$app->getQueue();
        if (!$queue instanceof IsolatedQueue) {
            throw new \RuntimeException('Translation Manager tests require the bootstrap-isolated Craft queue.');
        }

        $this->originalQueue = $queue;
        $queue->clearShadowRows();

        Craft::$app->set('queue', new Queue([
            'db' => $queue->db,
            'mutex' => $queue->mutex,
            'tableName' => $queue->tableName,
            'channel' => $queue->channel,
            'mutexTimeout' => $queue->mutexTimeout,
        ]));
    }

    private function finishIsolation(): void
    {
        if ($this->isolationFinished) {
            return;
        }
        $this->isolationFinished = true;
        $errors = [];

        $this->runCleanupStep($errors, fn() => $this->purgeMarkerRows());
        $this->runCleanupStep($errors, function(): void {
            foreach ($this->appComponentSnapshots as $id => $component) {
                Craft::$app->set($id, $component);
            }
            $this->appComponentSnapshots = [];
        });
        $this->runCleanupStep($errors, function(): void {
            if ($this->settingsSnapshot !== null) {
                TranslationManager::getInstance()->getSettings()->setAttributes($this->settingsSnapshot, false);
                $this->settingsSnapshot = null;
            }
        });
        $this->runCleanupStep($errors, function(): void {
            if ($this->originalQueue !== null) {
                Craft::$app->set('queue', $this->originalQueue);
                if ($this->originalQueue instanceof IsolatedQueue) {
                    $this->originalQueue->clearShadowRows();
                }
                $this->originalQueue = null;
            }
        });

        if ($this->baseStateInitialised) {
            $this->runCleanupStep($errors, fn() => parent::tearDown());
            $this->baseStateInitialised = false;
        }
        self::$activeTest = null;

        if ($errors !== []) {
            $messages = array_map(
                static fn(Throwable $error): string => $error::class . ': ' . $error->getMessage(),
                $errors,
            );
            throw new \RuntimeException(
                'Translation Manager test isolation cleanup failed: ' . implode(' | ', $messages),
                0,
                $errors[0],
            );
        }
    }

    /** @param list<Throwable> $errors */
    private function runCleanupStep(array &$errors, callable $cleanup): void
    {
        try {
            $cleanup();
        } catch (Throwable $exception) {
            $errors[] = $exception;
        }
    }
}
