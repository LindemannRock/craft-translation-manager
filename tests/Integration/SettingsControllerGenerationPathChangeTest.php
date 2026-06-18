<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\controllers\SettingsController;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\GenerationStatusService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.25.1
 */
#[CoversClass(SettingsController::class)]
final class SettingsControllerGenerationPathChangeTest extends TestCase
{
    public function testGenerationPathChangeHandlerRegeneratesFiles(): void
    {
        $plugin = TranslationManager::getInstance();
        $originalGenerate = $plugin->get('generate');
        $originalGenerationStatus = $plugin->get('generationStatus');
        $spy = new SettingsControllerGenerationPathSpyService();
        $statusSpy = new SettingsControllerGenerationStatusSpyService();

        $plugin->set('generate', $spy);
        $plugin->set('generationStatus', $statusSpy);

        try {
            $controller = new SettingsController('settings', $plugin);
            $method = new \ReflectionMethod($controller, 'regenerateGeneratedFilesAfterPathChange');
            $method->invoke($controller, '/old/translations', '/new/translations');

            self::assertSame(1, $spy->generateAllCalls);
            self::assertSame(1, $statusSpy->recordedGenerationResults);
        } finally {
            $plugin->set('generate', $originalGenerate);
            $plugin->set('generationStatus', $originalGenerationStatus);
        }
    }
}

final class SettingsControllerGenerationStatusSpyService extends GenerationStatusService
{
    public int $recordedGenerationResults = 0;

    /**
     * @param array<string,mixed> $result
     */
    public function recordGenerationResult(array $result, string $reason, string $triggerType): void
    {
        $this->recordedGenerationResults++;
    }
}

final class SettingsControllerGenerationPathSpyService extends GenerationService
{
    public int $generateAllCalls = 0;

    public function generateAll(): array
    {
        $this->generateAllCalls++;

        return [
            'success' => true,
            'translationCount' => 0,
            'writtenFileCount' => 0,
            'deletedFileCount' => 0,
            'results' => [],
        ];
    }
}
