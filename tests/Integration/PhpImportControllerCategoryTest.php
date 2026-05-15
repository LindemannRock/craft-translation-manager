<?php

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\controllers\PhpImportController;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Covers PHP file import category registration so imported rows do not end up
 * hidden behind an unconfigured category filter.
 *
 * @since 5.24.0
 */
final class PhpImportControllerCategoryTest extends TestCase
{
    public function testMissingCategoryRequiresAutomaticRegistration(): void
    {
        $controller = $this->createPhpImportController();
        $category = 'tm_php_import_' . bin2hex(random_bytes(4));

        $status = $this->invokePrivate($controller, 'getImportCategoryStatus', [$category]);

        self::assertTrue($status['requiresRegistration']);
        self::assertTrue($status['canAutoRegister']);
        self::assertStringContainsString($category, (string)$status['message']);
    }

    public function testReservedCategoryIsRejected(): void
    {
        $controller = $this->createPhpImportController();

        $status = $this->invokePrivate($controller, 'getImportCategoryStatus', ['site']);

        self::assertArrayHasKey('error', $status);
    }

    public function testRegisterImportCategoryAddsEnabledCategory(): void
    {
        $settings = Settings::loadFromDatabase();
        if ($settings->isOverriddenByConfig('translationCategories')) {
            self::markTestSkipped('translationCategories is config-overridden.');
        }

        $originalCategories = $settings->translationCategories;
        $category = 'tm_php_import_' . bin2hex(random_bytes(4));
        $controller = $this->createPhpImportController();

        try {
            $registered = $this->invokePrivate($controller, 'registerImportCategory', [$category]);
            self::assertTrue($registered);

            TranslationManager::getInstance()->setSettings([]);
            $enabledCategories = TranslationManager::getInstance()->getSettings()->getEnabledCategories();

            self::assertContains($category, $enabledCategories);
        } finally {
            $settings = Settings::loadFromDatabase();
            $settings->translationCategories = $originalCategories;
            $settings->saveToDatabase(['translationCategories']);
            TranslationManager::getInstance()->setSettings([]);
        }
    }

    private function createPhpImportController(): PhpImportController
    {
        return new PhpImportController('php-import', TranslationManager::getInstance());
    }

    /**
     * @param array<int,mixed> $args
     * @return mixed
     */
    private function invokePrivate(PhpImportController $controller, string $method, array $args): mixed
    {
        $reflection = new \ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($controller, $args);
    }
}
