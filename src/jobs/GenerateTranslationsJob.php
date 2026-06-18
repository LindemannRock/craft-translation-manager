<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Queue job for live-runtime translation file generation.
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\jobs;

use Craft;
use craft\queue\BaseJob;
use lindemannrock\base\traits\QueueTtrTrait;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\records\GenerationStatusRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\queue\RetryableJobInterface;

/**
 * Generate Translations Job
 *
 * @since 5.28.0
 */
class GenerateTranslationsJob extends BaseJob implements RetryableJobInterface
{
    use QueueTtrTrait;
    use LoggingTrait;

    /**
     * Stable token used by RecurringQueueHelper to identify pending freshness jobs.
     */
    public string $identityToken = 'generation-freshness';

    /**
     * @var string Generation reason stored in the status table.
     */
    public string $reason = GenerationStatusRecord::REASON_FRESHNESS_CHECK;

    /**
     * @var string|null Fingerprint expected when the job was queued.
     */
    public ?string $expectedFingerprint = null;

    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(TranslationManager::$plugin->id);
    }

    public function getDescription(): ?string
    {
        return TranslationManager::$plugin->getSettings()->getDisplayName() . ': '
            . Craft::t('translation-manager', 'Generate Translation Files');
    }

    public function canRetry($attempt, $error): bool
    {
        return false;
    }

    public function execute($queue): void
    {
        TranslationManager::getInstance()
            ->generationStatus
            ->runQueuedGeneration($this->reason, $this->expectedFingerprint);
    }
}
