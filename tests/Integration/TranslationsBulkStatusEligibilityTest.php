<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\controllers\TranslationsController;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;

#[CoversClass(TranslationsController::class)]
final class TranslationsBulkStatusEligibilityTest extends TestCase
{
    public function testEmptyRowsCannotBeBulkStatusChanged(): void
    {
        $record = new TranslationRecord();
        $record->status = 'pending';
        $record->translation = '';

        self::assertFalse($this->canBulkSetStatus($record, 'translated'));
    }

    public function testUnusedRowsCannotBeBulkStatusChanged(): void
    {
        $record = new TranslationRecord();
        $record->status = 'unused';
        $record->translation = 'Existing translation';

        self::assertFalse($this->canBulkSetStatus($record, 'translated'));
    }

    public function testTranslatedRowsCanBeBulkStatusChanged(): void
    {
        $record = new TranslationRecord();
        $record->status = 'pending';
        $record->translation = 'Existing translation';

        self::assertTrue($this->canBulkSetStatus($record, 'translated'));
    }

    public function testRowsAlreadyInTargetStatusCannotBeBulkStatusChanged(): void
    {
        $record = new TranslationRecord();
        $record->status = 'translated';
        $record->translation = 'Existing translation';

        self::assertFalse($this->canBulkSetStatus($record, 'translated'));
    }

    private function canBulkSetStatus(TranslationRecord $record, string $targetStatus): bool
    {
        $method = new ReflectionMethod(TranslationsController::class, 'canBulkSetStatus');
        $method->setAccessible(true);
        $controller = new TranslationsController('translations', TranslationManager::getInstance());

        return $method->invoke($controller, $record, $targetStatus);
    }
}
