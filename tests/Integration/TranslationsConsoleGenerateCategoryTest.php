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

final class TranslationsConsoleGenerateCategorySpyService extends GenerationService
{
    /**
     * @var string[]
     */
    public array $generatedCategories = [];

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
