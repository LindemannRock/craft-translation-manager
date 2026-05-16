<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\controllers\PhpImportController;
use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
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

    public function testSaveImportHistoryRecordsPhpImport(): void
    {
        $controller = $this->createPhpImportController();
        $file = 'ar/test-history.php';
        $user = \Craft::$app->getUsers()->getUserByUsernameOrEmail('admin');
        if ($user === null) {
            self::markTestSkipped('Test requires an admin user.');
        }
        $originalIdentity = \Craft::$app->getUser()->getIdentity();
        \Craft::$app->getUser()->setIdentity($user);

        try {
            $this->invokePrivate($controller, 'saveImportHistory', [
                $file,
                2,
                1,
                ['Example error'],
                'before_php_import_2026-05-15',
            ]);

            /** @var ImportHistoryRecord|null $record */
            $record = ImportHistoryRecord::find()
                ->where(['filename' => $file])
                ->orderBy(['id' => SORT_DESC])
                ->one();

            self::assertInstanceOf(ImportHistoryRecord::class, $record);
            self::assertSame(0, (int)$record->filesize);
            self::assertSame(2, (int)$record->imported);
            self::assertSame(1, (int)$record->updated);
            self::assertSame(0, (int)$record->skipped);
            self::assertSame('before_php_import_2026-05-15', $record->backupPath);
            self::assertNotEmpty($record->errors);
        } finally {
            \Craft::$app->getUser()->setIdentity($originalIdentity);
            ImportHistoryRecord::deleteAll(['filename' => $file]);
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
