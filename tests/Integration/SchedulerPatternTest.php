<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use craft\db\Query;
use lindemannrock\translationmanager\jobs\CreateBackupJob;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use ReflectionMethod;

/**
 * Verifies the recurring backup scheduler pattern.
 *
 * @since 5.25.0
 */
final class SchedulerPatternTest extends TestCase
{
    private bool $originalBackupEnabled;

    private string $originalBackupSchedule;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = TranslationManager::getInstance()->getSettings();
        $this->originalBackupEnabled = $settings->backupEnabled;
        $this->originalBackupSchedule = $settings->backupSchedule;

        $this->deleteBackupQueueRows();
    }

    protected function tearDown(): void
    {
        $this->deleteBackupQueueRows();

        $settings = TranslationManager::getInstance()->getSettings();
        $settings->backupEnabled = $this->originalBackupEnabled;
        $settings->backupSchedule = $this->originalBackupSchedule;

        parent::tearDown();
    }

    public function testBackupScheduleOptionsNormalizeLegacyManual(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();

        $settings->backupSchedule = 'manual';
        self::assertSame('disabled', $settings->getEffectiveBackupSchedule());

        self::assertSame(
            ['disabled', 'daily', 'weekly', 'monthly'],
            array_column($settings->getBackupScheduleOptions(), 'value'),
        );
    }

    public function testScheduledBackupReschedulesEvenWhenCurrentRowExists(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->backupEnabled = true;
        $settings->backupSchedule = 'daily';

        Craft::$app->getQueue()->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));

        $job = new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]);

        $scheduleNext = new ReflectionMethod($job, 'scheduleNextBackup');
        $scheduleNext->setAccessible(true);
        $scheduleNext->invoke($job);

        self::assertSame(2, $this->countBackupQueueRows());
    }

    public function testBackupBootstrapDoesNotDuplicateExistingDelayedBackupRow(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->backupEnabled = true;
        $settings->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));

        self::assertSame(1, $this->countBackupQueueRows());

        $scheduleBackup = new ReflectionMethod(TranslationManager::getInstance(), 'scheduleBackupJob');
        $scheduleBackup->setAccessible(true);
        $scheduleBackup->invoke(TranslationManager::getInstance());

        self::assertSame(1, $this->countBackupQueueRows());
    }

    public function testBackupBootstrapCollapsesDuplicatePendingBackupRows(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->backupEnabled = true;
        $settings->backupSchedule = 'daily';

        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        Craft::$app->getQueue()->delay(300)->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        self::assertSame(2, $this->countBackupQueueRows());

        $scheduleBackup = new ReflectionMethod(TranslationManager::getInstance(), 'scheduleBackupJob');
        $scheduleBackup->setAccessible(true);
        $scheduleBackup->invoke(TranslationManager::getInstance());

        self::assertSame(1, $this->countBackupQueueRows());
    }

    public function testScheduleChangeReplacesPendingBackupRow(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->backupEnabled = true;
        $settings->backupSchedule = 'daily';

        Craft::$app->getQueue()->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));

        $settings->backupSchedule = 'weekly';
        TranslationManager::getInstance()->handleBackupScheduleChange($settings, true, 'daily');

        self::assertSame(1, $this->countBackupQueueRows());

        $row = $this->latestBackupQueueRow();
        self::assertIsArray($row);
        self::assertStringContainsString('Scheduled auto backup', (string) $row['description']);
    }

    public function testDisabledScheduleCancelsPendingBackupRows(): void
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $settings->backupEnabled = true;
        $settings->backupSchedule = 'daily';

        Craft::$app->getQueue()->push(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));

        $settings->backupSchedule = 'disabled';
        TranslationManager::getInstance()->handleBackupScheduleChange($settings, true, 'daily');

        self::assertSame(0, $this->countBackupQueueRows());
    }

    private function countBackupQueueRows(): int
    {
        return (int) $this->backupQueueQuery()->count();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestBackupQueueRow(): ?array
    {
        $row = $this->backupQueueQuery()
            ->select(['id', 'description'])
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $row !== false ? $row : null;
    }

    private function deleteBackupQueueRows(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', [
                'and',
                ['like', 'job', 'translationmanager'],
                ['like', 'job', 'CreateBackupJob'],
            ])
            ->execute();
    }

    private function backupQueueQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'translationmanager'])
            ->andWhere(['like', 'job', 'CreateBackupJob']);
    }
}
