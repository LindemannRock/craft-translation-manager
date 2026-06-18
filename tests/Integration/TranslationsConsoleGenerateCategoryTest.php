<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\console\controllers\TranslationsController;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\GenerationStatusService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;
use yii\console\ExitCode;

/**
 * @since 5.25.1
 */
#[CoversClass(TranslationsController::class)]
final class TranslationsConsoleGenerateCategoryTest extends TestCase
{
    public function testGenerateAllAcceptsDelayOption(): void
    {
        $controller = new TranslationsConsoleGenerateAllSpyController('translations', TranslationManager::getInstance());

        self::assertContains('delay', $controller->options('generate-all'));
        self::assertContains('verify', $controller->options('generate-all'));
    }

    public function testGenerateAllRejectsInvalidDelay(): void
    {
        $plugin = TranslationManager::getInstance();
        $originalGenerate = $plugin->get('generate');
        $spy = new TranslationsConsoleGenerateCategorySpyService();

        $plugin->set('generate', $spy);

        try {
            $controller = new TranslationsConsoleGenerateAllSpyController('translations', $plugin);
            $controller->delay = 301;

            $exitCode = $controller->actionGenerateAll();

            self::assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
            self::assertFalse($spy->generatedAll);
            self::assertSame([], $controller->sleptSeconds);
        } finally {
            $plugin->set('generate', $originalGenerate);
        }
    }

    public function testGenerateAllWaitsBeforeGenerationWhenDelayIsSet(): void
    {
        $plugin = TranslationManager::getInstance();
        $originalGenerate = $plugin->get('generate');
        $originalGenerationStatus = $plugin->get('generationStatus');
        $spy = new TranslationsConsoleGenerateCategorySpyService();
        $statusSpy = new TranslationsConsoleGenerateStatusSpyService();

        $plugin->set('generate', $spy);
        $plugin->set('generationStatus', $statusSpy);

        try {
            $controller = new TranslationsConsoleGenerateAllSpyController('translations', $plugin);
            $controller->delay = 7;

            $exitCode = $controller->actionGenerateAll();

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertTrue($spy->generatedAll);
            self::assertSame(1, $statusSpy->recordedGenerationResults);
            self::assertSame([7], $controller->sleptSeconds);
        } finally {
            $plugin->set('generate', $originalGenerate);
            $plugin->set('generationStatus', $originalGenerationStatus);
        }
    }

    public function testGenerateAllRunsVerificationWhenRequested(): void
    {
        $plugin = TranslationManager::getInstance();
        $originalGenerate = $plugin->get('generate');
        $originalGenerationStatus = $plugin->get('generationStatus');
        $spy = new TranslationsConsoleGenerateCategorySpyService();
        $statusSpy = new TranslationsConsoleGenerateStatusSpyService();

        $plugin->set('generate', $spy);
        $plugin->set('generationStatus', $statusSpy);

        try {
            $controller = new TranslationsConsoleGenerateAllSpyController('translations', $plugin);
            $controller->verify = true;

            $exitCode = $controller->actionGenerateAll();

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertTrue($spy->generatedAll);
            self::assertTrue($controller->verified);
            self::assertSame(1, $statusSpy->recordedGenerationResults);
        } finally {
            $plugin->set('generate', $originalGenerate);
            $plugin->set('generationStatus', $originalGenerationStatus);
        }
    }

    public function testGenerateAllFailsWhenVerificationFails(): void
    {
        $plugin = TranslationManager::getInstance();
        $originalGenerate = $plugin->get('generate');
        $originalGenerationStatus = $plugin->get('generationStatus');
        $spy = new TranslationsConsoleGenerateCategorySpyService();
        $statusSpy = new TranslationsConsoleGenerateStatusSpyService();

        $plugin->set('generate', $spy);
        $plugin->set('generationStatus', $statusSpy);

        try {
            $controller = new TranslationsConsoleGenerateAllSpyController('translations', $plugin);
            $controller->verify = true;
            $controller->verificationResult = false;

            $exitCode = $controller->actionGenerateAll();

            self::assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
            self::assertTrue($spy->generatedAll);
            self::assertTrue($controller->verified);
            self::assertSame(1, $statusSpy->recordedGenerationResults);
        } finally {
            $plugin->set('generate', $originalGenerate);
            $plugin->set('generationStatus', $originalGenerationStatus);
        }
    }

    public function testGenerateCategoryCallsGenerationServiceForEnabledCategory(): void
    {
        $plugin = TranslationManager::getInstance();
        $settings = $plugin->getSettings();
        $category = $settings->getPrimaryCategory();
        $originalGenerate = $plugin->get('generate');
        $spy = new TranslationsConsoleGenerateCategorySpyService();

        $plugin->set('generate', $spy);

        try {
            $controller = new TranslationsController('translations', $plugin);
            $exitCode = $controller->actionGenerateCategory($category);

            self::assertSame(ExitCode::OK, $exitCode);
            self::assertSame([$category], $spy->generatedCategories);
        } finally {
            $plugin->set('generate', $originalGenerate);
        }
    }

    public function testGenerateCategoryRejectsDisabledCategory(): void
    {
        $plugin = TranslationManager::getInstance();
        $originalGenerate = $plugin->get('generate');
        $spy = new TranslationsConsoleGenerateCategorySpyService();

        $plugin->set('generate', $spy);

        try {
            $controller = new TranslationsController('translations', $plugin);
            $exitCode = $controller->actionGenerateCategory('not-enabled-' . bin2hex(random_bytes(4)));

            self::assertSame(ExitCode::UNSPECIFIED_ERROR, $exitCode);
            self::assertSame([], $spy->generatedCategories);
        } finally {
            $plugin->set('generate', $originalGenerate);
        }
    }
}

final class TranslationsConsoleGenerateStatusSpyService extends GenerationStatusService
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

final class TranslationsConsoleGenerateCategorySpyService extends GenerationService
{
    /**
     * @var string[]
     */
    public array $generatedCategories = [];

    public bool $generatedAll = false;

    public function generateAll(): array
    {
        $this->generatedAll = true;

        return [
            'success' => true,
            'translationCount' => 3,
            'writtenFileCount' => 1,
            'deletedFileCount' => 0,
            'results' => [
                'site' => [
                    'success' => true,
                    'type' => 'site',
                    'label' => 'Site',
                    'categories' => ['messages'],
                    'translationCount' => 3,
                    'writtenFileCount' => 1,
                    'deletedFileCount' => 0,
                    'warnings' => [],
                ],
            ],
        ];
    }

    public function generateCategoryTranslations(string $category): array
    {
        $this->generatedCategories[] = $category;

        return [
            'success' => true,
            'type' => 'category',
            'label' => ucfirst($category),
            'categories' => [$category],
            'translationCount' => 3,
            'writtenFileCount' => 1,
            'deletedFileCount' => 0,
            'warnings' => [],
        ];
    }
}

final class TranslationsConsoleGenerateAllSpyController extends TranslationsController
{
    /**
     * @var int[]
     */
    public array $sleptSeconds = [];

    public bool $verified = false;

    public bool $verificationResult = true;

    protected function sleepBeforeGenerate(int $seconds): void
    {
        $this->sleptSeconds[] = $seconds;
    }

    protected function verifyGeneratedTranslationRuntime(array $result): bool
    {
        $this->verified = true;

        return $this->verificationResult;
    }
}
