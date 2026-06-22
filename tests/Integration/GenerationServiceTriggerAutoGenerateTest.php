<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins the contract of {@see GenerationService::triggerAutoGenerate()}: the
 * single funnel that callers (save / save-all / set-status / import) use to
 * request automatic file generation. The setting check lives inside the
 * funnel so callers never gate themselves.
 *
 * @since 5.24.0
 */
final class GenerationServiceTriggerAutoGenerateTest extends TestCase
{
    public function testReturnsTrueAndCallsGenerateAllWhenSettingIsOn(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $original = $settings->autoGenerate;
        $settings->autoGenerate = true;

        $spy = $this->makeSpy();

        try {
            $result = $spy->triggerAutoGenerate();

            self::assertTrue($result, 'triggerAutoGenerate() should report true when the setting is on.');
            self::assertSame(1, $spy->generateAllCalls, 'generateAll() should be called exactly once.');
        } finally {
            $settings->autoGenerate = $original;
        }
    }

    public function testReturnsTrueAndCallsGenerateSourcesWhenSourcesAreProvided(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $original = $settings->autoGenerate;
        $settings->autoGenerate = true;

        $spy = $this->makeSpy();

        try {
            $result = $spy->triggerAutoGenerate(['messages', 'email', 'messages']);

            self::assertTrue($result, 'triggerAutoGenerate() should report true when the setting is on.');
            self::assertSame(0, $spy->generateAllCalls, 'generateAll() should not run for source-scoped edits.');
            self::assertSame([['messages', 'email', 'messages']], $spy->generateSourcesCalls);
        } finally {
            $settings->autoGenerate = $original;
        }
    }

    public function testReturnsFalseAndSkipsGenerateAllWhenSettingIsOff(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $original = $settings->autoGenerate;
        $settings->autoGenerate = false;

        $spy = $this->makeSpy();

        try {
            $result = $spy->triggerAutoGenerate();

            self::assertFalse($result, 'triggerAutoGenerate() should report false when the setting is off.');
            self::assertSame(0, $spy->generateAllCalls, 'generateAll() must not run when the setting is off.');
        } finally {
            $settings->autoGenerate = $original;
        }
    }

    /**
     * Subclass GenerationService so the inherited triggerAutoGenerate() calls
     * a counting stub instead of writing files to disk.
     */
    private function makeSpy(): GenerationService
    {
        return new class extends GenerationService {
            public int $generateAllCalls = 0;

            /**
             * @var list<array<int,string>>
             */
            public array $generateSourcesCalls = [];

            public function generateAll(): array
            {
                $this->generateAllCalls++;
                return ['success' => true, 'results' => []];
            }

            public function generateSources(array $sourceIds): array
            {
                $this->generateSourcesCalls[] = $sourceIds;
                return [];
            }
        };
    }
}
