<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Composer\InstalledVersions;
use Craft;
use craft\db\Command;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\queue\Queue;
use craft\services\Config;
use lindemannrock\base\helpers\DateRangeHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\queue\DeferredQueueJob;
use lindemannrock\base\queue\PortableQueueScheduler;
use lindemannrock\translationmanager\jobs\CreateBackupJob;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\ScheduledBackupScheduler;
use lindemannrock\translationmanager\tests\Support\IsolatedQueue;
use lindemannrock\translationmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;
use yii\log\Logger;
use yii\mutex\Mutex;
use yii\queue\sqs\Queue as SqsQueue;

/**
 * Pins Translation Manager's portable scheduled-backup queue lifecycle.
 *
 * @since 5.35.0
 */
final class ScheduledBackupQueueTest extends TestCase
{
    private const START_TIMESTAMP = 1_800_000_000;

    private ?RecordingBackupSqsQueue $proxyQueue = null;
    private bool $timePaused = false;

    protected function tearDown(): void
    {
        try {
            if ($this->timePaused) {
                DateTimeHelper::resume();
                $this->timePaused = false;
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testQueueStartsAsAnEmptyConnectionLocalShadow(): void
    {
        self::assertSame(0, (int)(new Query())->from('{{%queue}}')->count());
    }

    public function testRunnerFallbackRestoresTheQueueAndRemovesShadowRows(): void
    {
        $this->pushOwnedJob(new CreateBackupJob([
            'reason' => 'manual',
            'reschedule' => false,
        ]));
        self::assertSame(1, (int)(new Query())->from('{{%queue}}')->count());

        self::finishActiveTestIsolation();

        self::assertInstanceOf(IsolatedQueue::class, Craft::$app->getQueue());
        self::assertSame(0, (int)(new Query())->from('{{%queue}}')->count());
    }

    public function testApprovedBaseQueueRuntimeIsAvailableThroughReflection(): void
    {
        $basePath = InstalledVersions::getInstallPath('lindemannrock/craft-plugin-base');
        self::assertIsString($basePath);
        $helper = new ReflectionClass(\lindemannrock\base\helpers\RecurringQueueHelper::class);
        $scheduler = new ReflectionClass(PortableQueueScheduler::class);
        $handoff = new ReflectionClass(DeferredQueueJob::class);

        self::assertTrue($helper->hasMethod('ensurePending'));
        self::assertTrue($helper->hasMethod('deletePending'));
        self::assertTrue($scheduler->hasMethod('pushAt'));
        self::assertTrue($scheduler->hasMethod('continue'));
        self::assertTrue($scheduler->isFinal());
        self::assertTrue($handoff->isFinal());
        self::assertSame(realpath($basePath . '/src/helpers/RecurringQueueHelper.php'), $helper->getFileName());
        self::assertSame(realpath($basePath . '/src/queue/PortableQueueScheduler.php'), $scheduler->getFileName());
        self::assertSame(realpath($basePath . '/src/queue/DeferredQueueJob.php'), $handoff->getFileName());
    }

    public function testScheduleTokensLabelsAndLegacyManualNormalizationRemainStable(): void
    {
        self::assertSame([
            ['value' => 'disabled', 'label' => 'Disabled'],
            ['value' => 'daily', 'label' => 'Daily'],
            ['value' => 'weekly', 'label' => 'Weekly'],
            ['value' => 'monthly', 'label' => 'Monthly'],
        ], $this->settings()->getBackupScheduleOptions());

        $this->settings()->backupSchedule = 'manual';
        self::assertSame('disabled', $this->settings()->getEffectiveBackupSchedule());
        self::assertSame([
            'enabled' => false,
            'schedule' => 'disabled',
        ], $this->scheduledBackups->getEffectiveState($this->settings()));
    }

    #[DataProvider('scheduleBoundaryProvider')]
    public function testScheduleHelperRetainsFixedWallClockBoundaries(string $schedule, string $from, string $expected): void
    {
        $timezone = new \DateTimeZone(Craft::$app->getTimeZone());
        $next = ScheduleHelper::calculateNext($schedule, new \DateTime($from, $timezone));

        self::assertNotNull($next);
        self::assertSame($expected, $next->format('Y-m-d H:i:s'));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function scheduleBoundaryProvider(): iterable
    {
        $timezone = new \DateTimeZone(Craft::$app->getTimeZone());
        $weekStart = DateRangeHelper::getWeekStartIsoDay();
        $weekly = new \DateTime('2026-08-17 15:45:30', $timezone);
        $days = ($weekStart - (int)$weekly->format('N') + 7) % 7;
        if ($days === 0) {
            $days = 7;
        }
        $weekly->modify("+$days days")->setTime(0, 0, 0);

        yield 'daily midnight' => ['daily', '2026-08-17 15:45:30', '2026-08-18 00:00:00'];
        yield 'configured weekly boundary' => ['weekly', '2026-08-17 15:45:30', $weekly->format('Y-m-d H:i:s')];
        yield 'monthly same wall clock' => ['monthly', '2026-01-31 15:45:30', '2026-02-28 15:45:30'];
    }

    #[DataProvider('portableBoundaryProvider')]
    public function testSqsDelayBoundaryUsesADeferredHandoffOnlyAboveNineHundredSeconds(int $delay, string $expectedClass): void
    {
        $queue = $this->installPortableQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);

        PortableQueueScheduler::push(
            job: $this->recurringJob('boundary'),
            delay: $delay,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );

        $row = $this->onlyOwnerRow();
        self::assertInstanceOf($expectedClass, $this->unserializeJob($row));
        self::assertSame($delay <= 900 ? $delay : 900, (int)$row['delay']);
        self::assertSame([$delay <= 900 ? $delay : 900], $this->proxyDelays());
    }

    /**
     * @return iterable<string, array{int, class-string}>
     */
    public static function portableBoundaryProvider(): iterable
    {
        yield '900 seconds' => [900, CreateBackupJob::class];
        yield '901 seconds' => [901, DeferredQueueJob::class];
    }

    #[DataProvider('longScheduleProvider')]
    public function testLongSchedulesUseMultipleHandoffsWithoutCreatingAnEarlyBackup(string $schedule, int $delay): void
    {
        $queue = $this->installPortableQueue(true);
        $backup = new RecordingBackupService();
        $this->replacePluginComponent('backup', $backup);
        $this->pauseAt(self::START_TIMESTAMP);
        $target = self::START_TIMESTAMP + $delay;

        $firstId = PortableQueueScheduler::pushAt(
            job: $this->recurringJob($schedule),
            targetTimestamp: $target,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );
        self::assertNotNull($firstId);

        $this->pauseAt(self::START_TIMESTAMP + 900);
        self::assertTrue($queue->executeJob($firstId));
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($this->onlyOwnerRow()));
        self::assertSame(0, $backup->createCalls);

        $secondId = (string)$this->onlyOwnerRow()['id'];
        $this->pauseAt(self::START_TIMESTAMP + 1_800);
        self::assertTrue($queue->executeJob($secondId));
        self::assertInstanceOf(DeferredQueueJob::class, $this->unserializeJob($this->onlyOwnerRow()));
        self::assertSame(0, $backup->createCalls);

        $thirdId = (string)$this->onlyOwnerRow()['id'];
        $this->pauseAt($target - 300);
        self::assertTrue($queue->executeJob($thirdId));
        $consumerRow = $this->onlyOwnerRow();
        self::assertInstanceOf(CreateBackupJob::class, $this->unserializeJob($consumerRow));
        self::assertSame(300, (int)$consumerRow['delay']);
        self::assertSame(1024, (int)$consumerRow['priority']);
        self::assertSame(1800, (int)$consumerRow['ttr']);
        self::assertSame(0, $backup->createCalls);
        self::assertLessThanOrEqual(900, max($this->proxyDelays()));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function longScheduleProvider(): iterable
    {
        yield 'daily' => ['daily', 86_400];
        yield 'weekly' => ['weekly', 604_800];
        yield 'monthly' => ['monthly', 2_678_400];
    }

    public function testLateHandoffQueuesTheFinalConsumerWithZeroDelay(): void
    {
        $queue = $this->installPortableQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $jobId = PortableQueueScheduler::push(
            job: $this->recurringJob('late'),
            delay: 901,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );
        self::assertNotNull($jobId);

        $this->pauseAt(self::START_TIMESTAMP + 950);
        self::assertTrue($queue->executeJob($jobId));

        $row = $this->onlyOwnerRow();
        self::assertInstanceOf(CreateBackupJob::class, $this->unserializeJob($row));
        self::assertSame(0, (int)$row['delay']);
        self::assertSame([900, 0], $this->proxyDelays());
    }

    public function testLocalQueueRetainsTheCompleteNativeDelay(): void
    {
        $queue = $this->installPortableQueue(false);
        $this->pauseAt(self::START_TIMESTAMP);

        PortableQueueScheduler::push(
            job: $this->recurringJob('local'),
            delay: 604_800,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );

        $row = $this->onlyOwnerRow();
        self::assertInstanceOf(CreateBackupJob::class, $this->unserializeJob($row));
        self::assertSame(604_800, (int)$row['delay']);
        self::assertSame([], $this->proxyDelays());
    }

    public function testRecurringConsumerDescriptionAndSerializedQueueContractRemainExact(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $from = new \DateTime('2026-08-17 15:45:30', new \DateTimeZone(Craft::$app->getTimeZone()));
        $nextRunTime = $this->scheduledBackups->getNextRunTime($this->settings(), $from);
        self::assertNotNull($nextRunTime);
        $job = new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
            'recurringOwner' => ScheduledBackupScheduler::RECURRING_OWNER,
            'nextRunTime' => $nextRunTime,
        ]);

        self::assertSame(
            $this->settings()->getDisplayName() . ": Scheduled auto backup ($nextRunTime)",
            $job->getDescription(),
        );
        self::assertFalse($job->canRetry(1, new \RuntimeException('test')));
        self::assertSame(1800, $job->getTtr());

        $rowId = $this->pushOwnedJob($job, 300);
        $row = (new Query())->from('{{%queue}}')->where(['id' => $rowId])->one();
        self::assertIsArray($row);
        $serialized = $this->unserializeJob($row);
        self::assertInstanceOf(CreateBackupJob::class, $serialized);
        self::assertSame('scheduled', $serialized->reason);
        self::assertTrue($serialized->reschedule);
        self::assertSame(ScheduledBackupScheduler::RECURRING_OWNER, $serialized->recurringOwner);
        self::assertSame(1024, (int)$row['priority']);
        self::assertSame(1800, (int)$row['ttr']);
    }

    public function testBootstrapRetainsEarliestHealthyPhpLegacyRowAndRemovesNewOwnerCompetition(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $legacyPayload = $this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $earliestId = $this->insertPayload($legacyPayload, delay: 100);
        $this->insertPayload($legacyPayload, delay: 200);
        $this->pushOwnedJob($this->recurringJob('competing'), 300);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame([$earliestId], $this->legacyRowIds());
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testRepeatedBootstrapCreatesExactlyOneNewOwnerChain(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        $this->scheduledBackups->synchronize($this->settings());
        $firstId = $this->onlyOwnerRow()['id'];
        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame((string)$firstId, (string)$this->onlyOwnerRow()['id']);
    }

    public function testBusyLifecycleLockDefersBootstrapWithoutChangingQueueRows(): void
    {
        $ownerId = $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $legacyId = $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])), delay: 100);
        $before = $this->queueMetadata();
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::LIFECYCLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $logger = Craft::getLogger();
        $originalFlushInterval = $logger->flushInterval;
        $logger->flushInterval = PHP_INT_MAX;
        $logOffset = count($logger->messages);

        try {
            try {
                $this->scheduledBackups->synchronize($this->settings());
            } finally {
                Craft::$app->set('mutex', $originalMutex);
            }

            self::assertSame($before, $this->queueMetadata());
            self::assertSame([$legacyId], $this->legacyRowIds());
            self::assertSame((string)$ownerId, (string)$this->onlyOwnerRow()['id']);
            self::assertSame([ScheduledBackupScheduler::LIFECYCLE_MUTEX], $mutex->acquisitions);
            self::assertSame([0], $mutex->timeouts);
            self::assertSame([], $mutex->releases);
            $this->assertWarningLoggedSince(
                $logOffset,
                'Scheduled-backup bootstrap reconciliation deferred because the lifecycle lock is busy.',
            );
        } finally {
            $logger->flushInterval = $originalFlushInterval;
            Craft::$app->set('mutex', $originalMutex);
        }

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame([$legacyId], $this->legacyRowIds());
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testBusyPortableLockDefersBootstrapWithoutInspectingOrChangingQueueRows(): void
    {
        $legacyPayload = $this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $legacyIds = [
            $this->insertPayload($legacyPayload, delay: 100),
            $this->insertPayload($legacyPayload, delay: 200),
        ];
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::PORTABLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $before = $this->queueMetadata();
        $logOffset = count(Craft::getLogger()->messages);

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame($before, $this->queueMetadata());
        self::assertSame($legacyIds, $this->legacyRowIds());
        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([0, 0], $mutex->timeouts);
        self::assertSame([ScheduledBackupScheduler::LIFECYCLE_MUTEX], $mutex->releases);
        self::assertFalse($mutex->isHeld(ScheduledBackupScheduler::LIFECYCLE_MUTEX));
        $this->assertWarningLoggedSince(
            $logOffset,
            'Scheduled-backup bootstrap reconciliation deferred because the portable queue lock is busy.',
        );
    }

    public function testReplacementCancellationWaitsForThePortableLockBeforeDeletingRows(): void
    {
        $ownerId = $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $legacyId = $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])));
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::PORTABLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            $this->scheduledBackups->replace($this->settings());
            self::fail('Expected portable mutex failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unable to acquire the portable scheduled-backup queue lock.', $exception->getMessage());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame([$legacyId], $this->legacyRowIds());
        self::assertSame((string)$ownerId, (string)$this->onlyOwnerRow()['id']);
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
    }

    public function testDisabledBootstrapCancelsUnderLifecycleThenPortableLocks(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])));
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
    }

    public function testDisabledBootstrapRetriesCancellationAfterLockContention(): void
    {
        $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $this->insertPayload($this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ])));
        $before = $this->queueMetadata();
        $mutex = new SelectiveFailureMutex([ScheduledBackupScheduler::PORTABLE_MUTEX]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame($before, $this->queueMetadata());

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
    }

    public function testSchedulerDeferredHandoffCarriesThePortableMutex(): void
    {
        $queue = $this->installPortableQueue(true);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'monthly';

        $this->scheduledBackups->synchronize($this->settings());

        $row = $this->onlyOwnerRow();
        $handoff = $this->unserializeJob($row);
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame(ScheduledBackupScheduler::PORTABLE_MUTEX, $handoff->mutexName);
        self::assertSame($this->identityTokens(), $handoff->identityTokens);
        self::assertSame(1024, $handoff->priority);
        self::assertSame(1800, $handoff->ttr);

        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->markExecuting($queue, (string)$row['id']);
        $this->pauseAt($handoff->targetTimestamp - 900);

        try {
            $handoff->execute($queue);
        } finally {
            $this->markExecuting($queue, null);
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame([ScheduledBackupScheduler::PORTABLE_MUTEX], $mutex->acquisitions);
        self::assertSame([ScheduledBackupScheduler::PORTABLE_MUTEX], $mutex->releases);
    }

    public function testCanonicalPushAtTargetRemainsExactWhenPortableLockAcquisitionAdvancesTime(): void
    {
        $this->installPortableQueue(true);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'weekly';
        $target = $this->scheduledBackups->getNextRun($this->settings());
        self::assertNotNull($target);
        $this->pauseAt($target->getTimestamp() - 2_000);
        $mutex = new SelectiveFailureMutex([], $target->getTimestamp() - 1_000);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);

        try {
            $this->scheduledBackups->synchronize($this->settings());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        $row = $this->onlyOwnerRow();
        $handoff = $this->unserializeJob($row);
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        self::assertSame($target->getTimestamp(), $handoff->targetTimestamp);
        self::assertSame(900, (int)$row['delay']);
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
    }

    public function testNonRecurringAndNonScheduledLegacyShapesDoNotBlockBootstrap(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $preservedIds = [
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'scheduled', 'reschedule' => false]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'manual', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'restore', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'maintenance', 'reschedule' => true]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'console', 'reschedule' => true]), 50),
        ];

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame(
            array_map('intval', $preservedIds),
            array_map('intval', (new Query())->from('{{%queue}}')->where(['id' => $preservedIds])->orderBy(['id' => SORT_ASC])->column()),
        );
    }

    public function testJsonAndDeferredWrapperLegacyRowsAreRecognized(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $jsonId = $this->insertPayload(json_encode([
            'plugin' => 'translationmanager',
            'class' => 'CreateBackupJob',
            'reason' => 'scheduled',
            'reschedule' => true,
        ], JSON_THROW_ON_ERROR), delay: 100);
        $deferred = new DeferredQueueJob([
            'job' => new CreateBackupJob(['reason' => 'scheduled', 'reschedule' => true]),
            'targetTimestamp' => self::START_TIMESTAMP + 2_000,
            'identityTokens' => ['translationmanager', 'CreateBackupJob'],
            'mutexName' => ScheduledBackupScheduler::PORTABLE_MUTEX,
            'chainId' => 'legacy-wrapper-chain',
        ]);
        $this->insertPayload($this->serializeJob($deferred), delay: 200);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame([$jsonId], $this->legacyRowIds());
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testLegacyRecognitionRequiresExactPluginAndJobTokens(): void
    {
        $preservedIds = [
            $this->insertPayload('{"plugin":"translationmanager-addon","class":"CreateBackupJob","reason":"scheduled","reschedule":true}'),
            $this->insertPayload('{"plugin":"translationmanager","class":"NotCreateBackupJob","reason":"scheduled","reschedule":true}'),
        ];
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame(1, $this->countOwnerRows());
        self::assertSame(
            $preservedIds,
            array_map('intval', (new Query())->from('{{%queue}}')->where(['id' => $preservedIds])->orderBy(['id' => SORT_ASC])->column()),
        );
    }

    public function testFailedLegacyRowDoesNotBlockNewOwnerRecovery(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $legacyPayload = $this->serializeJob(new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
        ]));
        $failedId = $this->insertPayload($legacyPayload, fail: true);

        $this->scheduledBackups->synchronize($this->settings());

        self::assertSame([$failedId], $this->legacyRowIds());
        self::assertSame(1, $this->countOwnerRows());
    }

    public function testReplacementCancelsEveryOwnedStateAndPreservesOtherQueueFamilies(): void
    {
        $newPayload = $this->serializeJob($this->recurringJob('new'));
        $legacyPayload = $this->serializeJob(new CreateBackupJob(['reason' => 'scheduled', 'reschedule' => true]));
        foreach ([
            ['payload' => $newPayload, 'state' => 'pending'],
            ['payload' => $newPayload, 'state' => 'reserved'],
            ['payload' => $newPayload, 'state' => 'failed'],
            ['payload' => $legacyPayload, 'state' => 'pending'],
            ['payload' => $legacyPayload, 'state' => 'reserved'],
            ['payload' => $legacyPayload, 'state' => 'failed'],
        ] as $row) {
            $this->insertPayload(
                $row['payload'],
                fail: $row['state'] === 'failed',
                reserved: $row['state'] === 'reserved',
            );
        }

        $deferred = new DeferredQueueJob([
            'job' => $this->recurringJob('deferred'),
            'targetTimestamp' => self::START_TIMESTAMP + 2_000,
            'identityTokens' => $this->identityTokens(),
            'mutexName' => ScheduledBackupScheduler::PORTABLE_MUTEX,
            'chainId' => 'owned-deferred-chain',
        ]);
        foreach (['pending', 'reserved', 'failed'] as $state) {
            $this->insertPayload(
                $this->serializeJob($deferred),
                fail: $state === 'failed',
                reserved: $state === 'reserved',
            );
        }

        $preservedIds = [
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'manual', 'reschedule' => false]), 50),
            $this->pushOwnedJob(new CreateBackupJob(['reason' => 'import', 'reschedule' => true]), 50),
            $this->insertPayload('{"plugin":"translationmanager","class":"CleanupTranslationsJob","reason":"maintenance"}'),
            $this->insertPayload('{"plugin":"another-plugin","class":"CreateBackupJob","reason":"scheduled","reschedule":true}'),
        ];

        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->replace($this->settings());

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([], $this->legacyRowIds());
        self::assertSame(
            array_map('intval', $preservedIds),
            array_map('intval', (new Query())->from('{{%queue}}')->where(['id' => $preservedIds])->orderBy(['id' => SORT_ASC])->column()),
        );
    }

    public function testCancelledDeferredHandoffCannotResurrectTheSchedule(): void
    {
        $queue = $this->installPortableQueue(true);
        $this->pauseAt(self::START_TIMESTAMP);
        $jobId = PortableQueueScheduler::push(
            job: $this->recurringJob('cancelled'),
            delay: 901,
            identityTokens: $this->identityTokens(),
            mutexName: ScheduledBackupScheduler::PORTABLE_MUTEX,
            priority: 1024,
            ttr: 1800,
            queue: $queue,
        );
        self::assertNotNull($jobId);
        $handoff = $this->unserializeJob($this->onlyOwnerRow());
        self::assertInstanceOf(DeferredQueueJob::class, $handoff);
        $this->markExecuting($queue, $jobId);

        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';
        $this->scheduledBackups->replace($this->settings());
        $handoff->execute($queue);

        self::assertSame(0, $this->countOwnerRows());
        self::assertSame([900], $this->proxyDelays());
        $this->markExecuting($queue, null);
    }

    public function testEffectiveSettingsChangesReplaceDisableAndReenableWithoutUnchangedChurn(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->replace($this->settings());
        $firstId = $this->onlyOwnerRow()['id'];
        $dailyState = $this->scheduledBackups->getEffectiveState($this->settings());

        self::assertFalse($this->scheduledBackups->replaceIfChanged($this->settings(), $dailyState));
        self::assertSame((string)$firstId, (string)$this->onlyOwnerRow()['id']);

        $this->settings()->backupSchedule = 'weekly';
        self::assertTrue($this->scheduledBackups->replaceIfChanged($this->settings(), $dailyState));
        $weeklyId = $this->onlyOwnerRow()['id'];
        self::assertNotSame((string)$firstId, (string)$weeklyId);

        $weeklyState = $this->scheduledBackups->getEffectiveState($this->settings());
        $this->settings()->backupEnabled = false;
        self::assertTrue($this->scheduledBackups->replaceIfChanged($this->settings(), $weeklyState));
        self::assertSame(0, $this->countOwnerRows());

        $disabledState = $this->scheduledBackups->getEffectiveState($this->settings());
        $this->settings()->backupSchedule = 'monthly';
        self::assertFalse($this->scheduledBackups->replaceIfChanged($this->settings(), $disabledState));
        self::assertSame(0, $this->countOwnerRows());

        $this->settings()->backupEnabled = true;
        self::assertTrue($this->scheduledBackups->replaceIfChanged($this->settings(), $disabledState));
        self::assertSame(1, $this->countOwnerRows());
    }

    public function testSettingsReplacementUsesConfigOverriddenEffectiveValues(): void
    {
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->scheduledBackups->replace($this->settings());
        self::assertSame(1, $this->countOwnerRows());

        $config = $this->createMock(Config::class);
        $config->method('getConfigFromFile')->willReturnCallback(
            static fn(string $handle): array => $handle === 'translation-manager'
                ? ['backupEnabled' => false, 'backupSchedule' => 'weekly']
                : [],
        );
        Craft::$app->set('config', $config);

        $savedSettings = clone $this->settings();
        PluginHelper::applyConfigOverridesToSettings($savedSettings, 'translation-manager');
        $this->scheduledBackups->replaceIfChanged($savedSettings, [
            'enabled' => true,
            'schedule' => 'daily',
        ]);

        self::assertFalse($savedSettings->backupEnabled);
        self::assertSame('weekly', $savedSettings->backupSchedule);
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testDisabledReservedRecurringJobDoesNotCreateBackupOrSuccessor(): void
    {
        $backup = new RecordingBackupService();
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'disabled';

        $this->recurringJob('disabled')->execute(Craft::$app->getQueue());

        self::assertSame(0, $backup->createCalls);
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testManualBackupRunsDirectlyWithoutJoiningTheRecurringFamily(): void
    {
        $backup = new RecordingBackupService();
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupRetentionDays = 0;

        (new CreateBackupJob([
            'reason' => 'manual',
            'reschedule' => false,
        ]))->execute(Craft::$app->getQueue());

        self::assertSame(['manual'], $backup->reasons);
        self::assertSame(['create'], $backup->events);
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testSuccessfulRecurringBackupCleansRetentionBeforeQueuingOneSuccessor(): void
    {
        $backup = new RecordingBackupService();
        $this->replacePluginComponent('backup', $backup);
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->settings()->backupRetentionDays = 30;
        $backup->onCreate = static function() use ($mutex): void {
            self::assertTrue($mutex->isHeld(ScheduledBackupScheduler::LIFECYCLE_MUTEX));
            self::assertFalse($mutex->isHeld(ScheduledBackupScheduler::PORTABLE_MUTEX));
        };

        try {
            $this->recurringJob('success')->execute(Craft::$app->getQueue());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame(1, $backup->createCalls);
        self::assertSame(['scheduled'], $backup->reasons);
        self::assertSame(1, $backup->cleanupCalls);
        self::assertSame(['create', 'cleanup'], $backup->events);
        self::assertSame(1, $this->countOwnerRows());
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([5, 5], $mutex->timeouts);
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
    }

    public function testBackupFailurePropagatesWithoutQueuingASuccessor(): void
    {
        $backup = new RecordingBackupService();
        $backup->succeed = false;
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            $this->recurringJob('failure')->execute(Craft::$app->getQueue());
            self::fail('Expected backup creation failure to propagate.');
        } catch (\Exception $exception) {
            self::assertSame('Injected scheduled backup failure.', $exception->getMessage());
        }

        self::assertSame(1, $backup->createCalls);
        self::assertSame(0, $backup->cleanupCalls);
        self::assertSame(0, $this->countOwnerRows());
    }

    public function testEmptyStateNoOpQueuesSuccessorWithoutRetentionCleanup(): void
    {
        $backup = new RecordingBackupService();
        $backup->empty = true;
        $this->replacePluginComponent('backup', $backup);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $this->settings()->backupRetentionDays = 30;

        $this->recurringJob('empty')->execute(Craft::$app->getQueue());

        self::assertSame(1, $backup->createCalls);
        self::assertSame(['scheduled'], $backup->reasons);
        self::assertSame(0, $backup->cleanupCalls);
        self::assertSame(1, $this->countOwnerRows());
    }

    public function testSettingsReplacementPropagatesLifecycleAndPortableLockContention(): void
    {
        $originalMutex = Craft::$app->getMutex();
        $lifecycleMutex = new SelectiveFailureMutex([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ]);
        Craft::$app->set('mutex', $lifecycleMutex);
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';

        try {
            $this->scheduledBackups->replace($this->settings());
            self::fail('Expected lifecycle mutex failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unable to acquire the scheduled-backup lifecycle lock.', $exception->getMessage());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }
        self::assertSame([5], $lifecycleMutex->timeouts);

        $this->scheduledBackups->replace($this->settings());
        self::assertSame(1, $this->countOwnerRows());
        $portableMutex = new SelectiveFailureMutex([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ]);
        Craft::$app->set('mutex', $portableMutex);

        try {
            $this->settings()->backupEnabled = false;
            $this->scheduledBackups->replace($this->settings());
            self::fail('Expected portable mutex failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unable to acquire the portable scheduled-backup queue lock.', $exception->getMessage());
            self::assertSame(1, $this->countOwnerRows());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
        }
        self::assertSame([5, 5], $portableMutex->timeouts);
        self::assertSame([ScheduledBackupScheduler::LIFECYCLE_MUTEX], $portableMutex->releases);
    }

    public function testCancellationFailureAfterLockAcquisitionPropagatesAndPreservesRows(): void
    {
        $ownerId = $this->pushOwnedJob($this->recurringJob('owner'), 300);
        $this->settings()->backupEnabled = false;
        $this->settings()->backupSchedule = 'disabled';
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        $db = Craft::$app->getDb();
        $originalCommandClass = $db->commandClass;
        Craft::$app->set('mutex', $mutex);
        $db->commandClass = FailingQueueDeleteCommand::class;

        try {
            $this->scheduledBackups->replace($this->settings());
            self::fail('Expected cancellation failure to propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Scheduled-backup cancellation failure.', $exception->getMessage());
        } finally {
            $db->commandClass = $originalCommandClass;
            Craft::$app->set('mutex', $originalMutex);
        }

        self::assertSame((string)$ownerId, (string)$this->onlyOwnerRow()['id']);
        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
    }

    public function testQueuePushFailurePropagatesAndLeavesInspectableOwnedRow(): void
    {
        $this->installPortableQueue(true);
        self::assertNotNull($this->proxyQueue);
        $this->proxyQueue->failPushes = true;
        $this->settings()->backupEnabled = true;
        $this->settings()->backupSchedule = 'daily';
        $mutex = new SelectiveFailureMutex([]);
        $originalMutex = Craft::$app->getMutex();
        Craft::$app->set('mutex', $mutex);

        try {
            $this->scheduledBackups->replace($this->settings());
            self::fail('Expected queue push failure to propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Scheduled backup proxy failure.', $exception->getMessage());
        } finally {
            Craft::$app->set('mutex', $originalMutex);
            self::assertSame(1, $this->countOwnerRows());
        }

        self::assertSame([
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
            ScheduledBackupScheduler::PORTABLE_MUTEX,
        ], $mutex->acquisitions);
        self::assertSame([
            ScheduledBackupScheduler::PORTABLE_MUTEX,
            ScheduledBackupScheduler::LIFECYCLE_MUTEX,
        ], $mutex->releases);
    }

    public function testRuntimeDependsOnlyOnPublishedQueueContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = file_get_contents($root . '/src/services/ScheduledBackupScheduler.php')
            . file_get_contents($root . '/src/jobs/CreateBackupJob.php');
        $composer = file_get_contents($root . '/composer.json');

        self::assertIsString($runtime);
        self::assertIsString($composer);
        self::assertStringNotContainsString('craft\\cloud', $runtime);
        self::assertStringNotContainsString('craftcms/cloud', $composer);
        self::assertStringNotContainsString('AWS_', $runtime);
        self::assertStringNotContainsString('CLOUD_', $runtime);
    }

    private function installPortableQueue(bool $bounded): Queue
    {
        $current = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $current);
        $this->proxyQueue = $bounded ? new RecordingBackupSqsQueue() : null;
        $queue = new Queue([
            'db' => Craft::$app->getDb(),
            'mutex' => Craft::$app->getMutex(),
            'tableName' => $current->tableName,
            'channel' => $current->channel,
            'mutexTimeout' => $current->mutexTimeout,
            'proxyQueue' => $this->proxyQueue,
        ]);
        Craft::$app->set('queue', $queue);

        $installedQueue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $installedQueue);

        return $installedQueue;
    }

    private function pauseAt(int $timestamp): void
    {
        if ($this->timePaused) {
            DateTimeHelper::resume();
        }

        DateTimeHelper::pause(new \DateTime("@$timestamp"));
        $this->timePaused = true;
    }

    private function recurringJob(string $nextRunTime): CreateBackupJob
    {
        return new CreateBackupJob([
            'reason' => 'scheduled',
            'reschedule' => true,
            'recurringOwner' => ScheduledBackupScheduler::RECURRING_OWNER,
            'nextRunTime' => $nextRunTime,
        ]);
    }

    /** @return non-empty-list<string> */
    private function identityTokens(): array
    {
        return [
            ScheduledBackupScheduler::PLUGIN_TOKEN,
            'CreateBackupJob',
            ScheduledBackupScheduler::RECURRING_OWNER,
        ];
    }

    /** @return array<string, mixed> */
    private function onlyOwnerRow(): array
    {
        $rows = $this->ownerQuery()->orderBy(['id' => SORT_ASC])->all();
        self::assertCount(1, $rows);

        return $rows[0];
    }

    private function countOwnerRows(): int
    {
        return (int)$this->ownerQuery()->count();
    }

    private function ownerQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', ScheduledBackupScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['like', 'job', ScheduledBackupScheduler::RECURRING_OWNER]);
    }

    /** @return list<array<string, mixed>> */
    private function queueMetadata(): array
    {
        return (new Query())
            ->from('{{%queue}}')
            ->select(['id', 'channel', 'timePushed', 'ttr', 'delay', 'priority', 'timeUpdated', 'fail'])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    private function assertWarningLoggedSince(int $offset, string $expectedMessage): void
    {
        foreach (array_slice(Craft::getLogger()->messages, $offset) as $message) {
            if ($message[0] === $expectedMessage
                && $message[1] === Logger::LEVEL_WARNING
                && $message[2] === 'translation-manager'
            ) {
                self::addToAssertionCount(1);
                return;
            }
        }

        self::fail("Expected translation-manager warning was not logged: $expectedMessage");
    }

    /** @return list<int> */
    private function legacyRowIds(): array
    {
        $ids = [];
        $rows = (new Query())
            ->from('{{%queue}}')
            ->select(['id', 'job'])
            ->where(['like', 'job', ScheduledBackupScheduler::PLUGIN_TOKEN])
            ->andWhere(['like', 'job', 'CreateBackupJob'])
            ->andWhere(['not like', 'job', ScheduledBackupScheduler::RECURRING_OWNER])
            ->orderBy(['id' => SORT_ASC])
            ->all();
        foreach ($rows as $row) {
            $payload = (string)$row['job'];
            if ((str_contains($payload, 's:6:"reason";s:9:"scheduled";')
                    && str_contains($payload, 's:10:"reschedule";b:1;'))
                || (str_contains($payload, '"reason":"scheduled"')
                    && str_contains($payload, '"reschedule":true'))
            ) {
                $ids[] = (int)$row['id'];
            }
        }

        return $ids;
    }

    private function serializeJob(object $job): string
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);

        return $queue->serializer->serialize($job);
    }

    /** @param array<string, mixed> $row */
    private function unserializeJob(array $row): object
    {
        $queue = Craft::$app->getQueue();
        self::assertInstanceOf(Queue::class, $queue);
        $job = $queue->serializer->unserialize((string)$row['job']);
        self::assertIsObject($job);

        return $job;
    }

    private function insertPayload(
        string $payload,
        int $delay = 300,
        bool $fail = false,
        bool $reserved = false,
    ): int {
        Craft::$app->getDb()->createCommand()->insert('{{%queue}}', [
            'channel' => 'queue',
            'job' => $payload,
            'description' => 'Translation Manager queue test row',
            'timePushed' => DateTimeHelper::currentTimeStamp(),
            'ttr' => 1800,
            'delay' => $delay,
            'priority' => 1024,
            'timeUpdated' => $reserved ? DateTimeHelper::currentTimeStamp() : null,
            'fail' => $fail,
        ])->execute();

        return (int)Craft::$app->getDb()->getLastInsertID();
    }

    /** @return list<int> */
    private function proxyDelays(): array
    {
        return $this->proxyQueue === null ? [] : array_column($this->proxyQueue->pushes, 'delay');
    }

    private function markExecuting(Queue $queue, ?string $jobId): void
    {
        if ($jobId !== null) {
            Craft::$app->getDb()->createCommand()
                ->update('{{%queue}}', ['timeUpdated' => DateTimeHelper::currentTimeStamp()], ['id' => $jobId])
                ->execute();
        }

        $property = new ReflectionProperty(Queue::class, '_executingJobId');
        $property->setValue($queue, $jobId);
    }
}

/** Records bounded proxy pushes without contacting a provider. */
final class RecordingBackupSqsQueue extends SqsQueue
{
    /** @var list<array{delay: int, priority: mixed, ttr: int}> */
    public array $pushes = [];

    public bool $failPushes = false;

    protected function pushMessage($message, $ttr, $delay, $priority): string
    {
        if ($this->failPushes) {
            throw new \RuntimeException('Scheduled backup proxy failure.');
        }

        $this->pushes[] = [
            'delay' => (int)$delay,
            'priority' => $priority,
            'ttr' => (int)$ttr,
        ];

        return 'scheduled-backup-proxy-' . count($this->pushes);
    }
}

/** Backup seam that never writes files or volume objects. */
final class RecordingBackupService extends BackupService
{
    public int $createCalls = 0;
    public int $cleanupCalls = 0;
    public bool $succeed = true;
    public bool $empty = false;
    /** @var list<string> */
    public array $reasons = [];
    /** @var list<string> */
    public array $events = [];
    public ?\Closure $onCreate = null;

    public function createBackup(?string $reason = null): ?string
    {
        ($this->onCreate ?? static fn() => null)();
        $this->createCalls++;
        $this->reasons[] = $reason ?? 'import';
        $this->events[] = 'create';

        if (!$this->succeed) {
            throw new \RuntimeException('Injected scheduled backup failure.');
        }

        if ($this->empty) {
            return null;
        }

        return '/tmp/translation-manager-owned-test-backup';
    }

    public function cleanupOldBackups(): int
    {
        $this->cleanupCalls++;
        $this->events[] = 'cleanup';

        return 0;
    }
}

/** Mutex seam that fails only explicitly named locks. */
final class SelectiveFailureMutex extends Mutex
{
    /** @var list<string> */
    public array $acquisitions = [];
    /** @var list<string> */
    public array $releases = [];
    /** @var list<int> */
    public array $timeouts = [];
    /** @var list<string> */
    private array $heldNames = [];

    /** @param list<string> $failedNames */
    public function __construct(
        private readonly array $failedNames,
        private readonly ?int $portableTimestamp = null,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    protected function acquireLock($name, $timeout = 0): bool
    {
        $this->acquisitions[] = (string)$name;
        $this->timeouts[] = (int)$timeout;
        if ($name === ScheduledBackupScheduler::PORTABLE_MUTEX && $this->portableTimestamp !== null) {
            DateTimeHelper::resume();
            DateTimeHelper::pause(new \DateTime('@' . $this->portableTimestamp));
        }

        if (in_array($name, $this->failedNames, true)) {
            return false;
        }

        $this->heldNames[] = (string)$name;

        return true;
    }

    protected function releaseLock($name): bool
    {
        $this->releases[] = (string)$name;
        $this->heldNames = array_values(array_filter(
            $this->heldNames,
            static fn(string $heldName): bool => $heldName !== (string)$name,
        ));

        return true;
    }

    public function isHeld(string $name): bool
    {
        return in_array($name, $this->heldNames, true);
    }
}

/** Simulates a database failure only when the scheduler cancels queue rows. */
final class FailingQueueDeleteCommand extends Command
{
    public function execute()
    {
        $sql = ltrim($this->getSql());
        if (str_starts_with(strtoupper($sql), 'DELETE FROM') && str_contains($sql, 'queue')) {
            throw new \RuntimeException('Scheduled-backup cancellation failure.');
        }

        return parent::execute();
    }
}
