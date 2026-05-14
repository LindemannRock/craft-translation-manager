<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\base\helpers\ScheduleHelper;
use lindemannrock\base\traits\QueueTtrTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\queue\RetryableJobInterface;

/**
 * Recheck Usage Job
 *
 * Walks every enabled form provider (currently Formie; Freeform later) to
 * rebuild the "active text" set, then batch-updates Formie translation
 * statuses so the index can read pre-computed values instead of paying
 * the traversal cost on every page load.
 *
 * Self-rescheduling: when `$reschedule = true`, the job pushes its next
 * occurrence after running, using the schedule string from settings.
 *
 * @since 5.24.0
 */
class RecheckUsageJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;
    use LoggingTrait;

    /**
     * @var bool Whether to reschedule a follow-up run after completion
     */
    public bool $reschedule = false;

    /**
     * @var string|null Display string for the next scheduled run
     */
    public ?string $nextRunTime = null;

    /**
     * @inheritdoc
     */
    public function canRetry($attempt, $error): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle('translation-manager');

        if ($this->reschedule && !$this->nextRunTime) {
            $next = ScheduleHelper::calculateNext(
                TranslationManager::$plugin->getSettings()->usageRecheckSchedule
            );
            if ($next !== null) {
                // calculateNext returns DateTime in Craft TZ — pass isUtc=false
                $this->nextRunTime = DateFormatHelper::formatCompactDatetime($next, false, false);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        $result = TranslationManager::$plugin->translations->recheckUsage();

        $this->logInfo('Usage recheck completed', $result);

        if ($this->reschedule) {
            $this->scheduleNextRecheck();
        }
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): ?string
    {
        $pluginName = TranslationManager::$plugin->getSettings()->getDisplayName();
        $description = Craft::t('translation-manager', '{pluginName}: Rechecking translation usage', [
            'pluginName' => $pluginName,
        ]);

        if ($this->nextRunTime) {
            $description .= " ({$this->nextRunTime})";
        }

        return $description;
    }

    /**
     * Schedule the next occurrence using the plugin's configured schedule.
     *
     * Called from inside execute(), so by definition we are the only
     * currently-running instance — no LIKE-against-queue dedup is
     * needed (and it would false-positive against the still-reserved
     * row of the running job, killing the self-reschedule entirely).
     * Bootstrap dedup is handled by the cache flag in
     * `TranslationManager::scheduleRecheckUsageJob()`.
     */
    private function scheduleNextRecheck(): void
    {
        $settings = TranslationManager::$plugin->getSettings();

        if (!$settings->enableScheduledUsageRecheck) {
            return;
        }

        $next = ScheduleHelper::calculateNext($settings->usageRecheckSchedule);
        if ($next === null) {
            return;
        }

        $delay = $next->getTimestamp() - time();
        if ($delay <= 0) {
            return;
        }

        $job = new self([
            'reschedule' => true,
            'nextRunTime' => DateFormatHelper::formatCompactDatetime($next, false, false),
        ]);

        Craft::$app->getQueue()->delay($delay)->push($job);

        $this->logDebug('Scheduled next usage recheck', [
            'delay' => $delay,
            'nextRun' => $next->format('Y-m-d H:i T'),
        ]);
    }
}
