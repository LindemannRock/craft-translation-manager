<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Tracks generated translation file freshness for live runtime regeneration.
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\Json;
use lindemannrock\base\helpers\RecurringQueueHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\jobs\GenerateTranslationsJob;
use lindemannrock\translationmanager\records\GenerationStatusRecord;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\db\Expression;

/**
 * Generation Status Service
 *
 * @since 5.28.0
 */
class GenerationStatusService extends Component
{
    use LoggingTrait;

    private const FINGERPRINT_VERSION = 1;
    private const QUEUE_IDENTITY_TOKEN = 'generation-freshness';
    private const FAILED_RETRY_GRACE_SECONDS = 900;

    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(TranslationManager::$plugin->id);
    }

    /**
     * Queue one live-runtime generation job when current generated files are
     * missing or stale relative to translated DB rows and generation settings.
     */
    public function maybeQueueFreshnessGeneration(): void
    {
        if (!$this->isStatusTableReady()) {
            return;
        }

        $state = $this->getCurrentState();
        if ($state['translatedRowCount'] === 0) {
            $this->recordNoopState($state);
            return;
        }

        $latest = $this->getLatestRecord();
        if ($latest !== null && $latest->fingerprint === $state['fingerprint']) {
            if ($this->isFreshRecord($latest, $state)) {
                return;
            }

            if ($latest->status === GenerationStatusRecord::STATUS_FAILED && $this->isRecent($latest->dateFinished)) {
                return;
            }
        }

        RecurringQueueHelper::ensurePending(
            pluginToken: 'translationmanager',
            jobClass: GenerateTranslationsJob::class,
            delay: 1,
            jobFactory: fn() => new GenerateTranslationsJob([
                'reason' => GenerationStatusRecord::REASON_FRESHNESS_CHECK,
                'expectedFingerprint' => $state['fingerprint'],
            ]),
            extraLikeTokens: [self::QUEUE_IDENTITY_TOKEN],
        );
    }

    /**
     * Run generation from the queue and persist a status row.
     */
    public function runQueuedGeneration(string $reason, ?string $expectedFingerprint = null): GenerationStatusRecord
    {
        $state = $this->getCurrentState();
        $record = $this->createStartedRecord($state, $reason, GenerationStatusRecord::TRIGGER_QUEUE);

        try {
            if ($expectedFingerprint !== null && $expectedFingerprint !== $state['fingerprint']) {
                $record->status = GenerationStatusRecord::STATUS_NOOP;
                $record->verificationStatus = GenerationStatusRecord::VERIFICATION_SKIPPED;
                $record->message = 'Generation skipped because the freshness fingerprint changed before the job ran.';
                $record->details = Json::encode([
                    'expectedFingerprint' => $expectedFingerprint,
                    'currentState' => $state,
                ]);
                $this->finishRecord($record);
                return $record;
            }

            if ($state['translatedRowCount'] === 0) {
                $record->status = GenerationStatusRecord::STATUS_NOOP;
                $record->verificationStatus = GenerationStatusRecord::VERIFICATION_SKIPPED;
                $record->message = 'Generation skipped because no translated rows were found.';
                $record->details = Json::encode(['currentState' => $state]);
                $this->finishRecord($record);
                return $record;
            }

            $result = TranslationManager::getInstance()->generate->generateAll();
            $this->applyResultToRecord($record, $result, $state);
            $this->finishRecord($record);

            return $record;
        } catch (\Throwable $e) {
            $record->status = GenerationStatusRecord::STATUS_FAILED;
            $record->verificationStatus = GenerationStatusRecord::VERIFICATION_FAILED;
            $record->message = $e->getMessage();
            $record->details = Json::encode(['currentState' => $state]);
            $this->finishRecord($record);
            throw $e;
        }
    }

    /**
     * Persist the result of an explicit CP/CLI generation run.
     *
     * @param array<string,mixed> $result
     */
    public function recordGenerationResult(array $result, string $reason, string $triggerType): void
    {
        if (!$this->isStatusTableReady()) {
            return;
        }

        $state = $this->getCurrentState();
        $record = $this->createStartedRecord($state, $reason, $triggerType);
        $this->applyResultToRecord($record, $result, $state);
        $this->finishRecord($record);
    }

    /**
     * @return array{
     *     fingerprint:string,
     *     generationPath:string,
     *     categories:string[],
     *     languages:string[],
     *     translatedRowCount:int,
     *     latestTranslationId:int,
     *     latestTranslationDate:string|null,
     *     rows:list<array{category:string,language:string,count:int,latestId:int,latestDate:string|null}>
     * }
     */
    public function getCurrentState(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $categories = $this->getGenerationCategories();
        $rows = $this->getTranslatedRowSummary($categories);

        $languages = [];
        $translatedRowCount = 0;
        $latestTranslationId = 0;
        $latestTranslationDate = null;

        foreach ($rows as $row) {
            $languages[] = $row['language'];
            $translatedRowCount += $row['count'];
            $latestTranslationId = max($latestTranslationId, $row['latestId']);

            if ($row['latestDate'] !== null && ($latestTranslationDate === null || $row['latestDate'] > $latestTranslationDate)) {
                $latestTranslationDate = $row['latestDate'];
            }
        }

        $languages = array_values(array_unique($languages));
        sort($languages);

        $payload = [
            'version' => self::FINGERPRINT_VERSION,
            'generationPath' => $settings->getGenerationPath(),
            'categories' => $categories,
            'languages' => $languages,
            'localeMapping' => $settings->getActiveLocaleMapping(),
            'sourceLanguage' => $settings->sourceLanguage,
            'rows' => $rows,
        ];

        return [
            'fingerprint' => hash('sha256', Json::encode($payload)),
            'generationPath' => $settings->getGenerationPath(),
            'categories' => $categories,
            'languages' => $languages,
            'translatedRowCount' => $translatedRowCount,
            'latestTranslationId' => $latestTranslationId,
            'latestTranslationDate' => $latestTranslationDate,
            'rows' => $rows,
        ];
    }

    public function getLatestRecord(): ?GenerationStatusRecord
    {
        /** @var GenerationStatusRecord|null $record */
        $record = GenerationStatusRecord::find()
            ->orderBy(['id' => SORT_DESC])
            ->one();

        return $record;
    }

    private function isStatusTableReady(): bool
    {
        try {
            return Craft::$app->getDb()
                ->getSchema()
                ->getTableSchema(GenerationStatusRecord::tableName()) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return string[]
     */
    private function getGenerationCategories(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');

        $categories = [];
        foreach ($integrationService->getEnabledIntegrations() as $integration) {
            $categories[] = $integration->getCategory();
        }

        foreach ($settings->getEnabledCategories() as $category) {
            $categories[] = $category;
        }

        $categories = array_values(array_unique(array_filter($categories)));
        sort($categories);

        return $categories;
    }

    /**
     * @param string[] $categories
     * @return list<array{category:string,language:string,count:int,latestId:int,latestDate:string|null}>
     */
    private function getTranslatedRowSummary(array $categories): array
    {
        if ($categories === []) {
            return [];
        }

        $settings = TranslationManager::getInstance()->getSettings();
        /** @var list<array{category:string,language:string|null,count:string|int,latestId:string|int,latestDate:string|null}> $rows */
        $rows = (new Query())
            ->select([
                'category',
                'language',
                'count' => new Expression('COUNT([[id]])'),
                'latestId' => new Expression('MAX([[id]])'),
                'latestDate' => new Expression('MAX([[dateUpdated]])'),
            ])
            ->from(TranslationRecord::tableName())
            ->where([
                'category' => $categories,
                'status' => 'translated',
            ])
            ->andWhere(['not', ['translation' => null]])
            ->andWhere(['<>', 'translation', ''])
            ->groupBy(['category', 'language'])
            ->orderBy(['category' => SORT_ASC, 'language' => SORT_ASC])
            ->all();

        $summary = [];
        foreach ($rows as $row) {
            $language = (string)($row['language'] ?: $settings->sourceLanguage);
            $summary[] = [
                'category' => (string)$row['category'],
                'language' => $settings->mapLanguage($language),
                'count' => (int)$row['count'],
                'latestId' => (int)$row['latestId'],
                'latestDate' => $row['latestDate'] !== null ? (string)$row['latestDate'] : null,
            ];
        }

        usort(
            $summary,
            static fn(array $a, array $b): int => [$a['category'], $a['language']] <=> [$b['category'], $b['language']]
        );

        return $summary;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function isFreshRecord(GenerationStatusRecord $record, array $state): bool
    {
        if (!in_array($record->status, [GenerationStatusRecord::STATUS_SUCCESS, GenerationStatusRecord::STATUS_NOOP], true)) {
            return false;
        }

        if ($record->status === GenerationStatusRecord::STATUS_NOOP) {
            return $state['translatedRowCount'] === 0;
        }

        if (in_array($record->triggerType, [GenerationStatusRecord::TRIGGER_QUEUE, GenerationStatusRecord::TRIGGER_CP], true)) {
            return true;
        }

        return !$this->hasMissingGeneratedFiles($state);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function hasMissingGeneratedFiles(array $state): bool
    {
        $basePath = (string)$state['generationPath'];
        foreach ($state['rows'] as $row) {
            $file = $basePath . DIRECTORY_SEPARATOR . $row['language'] . DIRECTORY_SEPARATOR . $row['category'] . '.php';
            if (!is_file($file)) {
                return true;
            }
        }

        return false;
    }

    private function isRecent(?string $date): bool
    {
        if ($date === null) {
            return false;
        }

        return strtotime($date) >= time() - self::FAILED_RETRY_GRACE_SECONDS;
    }

    /**
     * @param array<string,mixed> $state
     */
    private function recordNoopState(array $state): void
    {
        $latest = $this->getLatestRecord();
        if ($latest !== null && $latest->fingerprint === $state['fingerprint'] && $latest->status === GenerationStatusRecord::STATUS_NOOP) {
            return;
        }

        $record = $this->createStartedRecord(
            $state,
            GenerationStatusRecord::REASON_FRESHNESS_CHECK,
            GenerationStatusRecord::TRIGGER_RUNTIME,
        );
        $record->status = GenerationStatusRecord::STATUS_NOOP;
        $record->verificationStatus = GenerationStatusRecord::VERIFICATION_SKIPPED;
        $record->message = 'Generation skipped because no translated rows were found.';
        $record->details = Json::encode(['currentState' => $state]);
        $this->finishRecord($record);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function createStartedRecord(array $state, string $reason, string $triggerType): GenerationStatusRecord
    {
        $now = Db::prepareDateForDb(new \DateTimeImmutable());
        $record = new GenerationStatusRecord();
        $record->fingerprint = (string)$state['fingerprint'];
        $record->status = GenerationStatusRecord::STATUS_RUNNING;
        $record->reason = $reason;
        $record->triggerType = $triggerType;
        $record->generationPath = (string)$state['generationPath'];
        $record->dateStarted = $now;
        $record->dateCreated = $now;
        $record->dateUpdated = $now;
        $record->save(false);

        return $record;
    }

    /**
     * @param array<string,mixed> $result
     * @param array<string,mixed> $state
     */
    private function applyResultToRecord(GenerationStatusRecord $record, array $result, array $state): void
    {
        $record->status = ($result['success'] ?? false)
            ? GenerationStatusRecord::STATUS_SUCCESS
            : GenerationStatusRecord::STATUS_FAILED;
        $record->translationCount = (int)($result['translationCount'] ?? 0);
        $record->writtenFileCount = (int)($result['writtenFileCount'] ?? 0);
        $record->deletedFileCount = (int)($result['deletedFileCount'] ?? 0);
        $record->verificationStatus = $record->status === GenerationStatusRecord::STATUS_SUCCESS
            ? GenerationStatusRecord::VERIFICATION_PASSED
            : GenerationStatusRecord::VERIFICATION_FAILED;
        $record->message = $record->status === GenerationStatusRecord::STATUS_SUCCESS
            ? 'Translation files generated successfully.'
            : 'Translation file generation failed.';
        $record->details = Json::encode([
            'currentState' => $state,
            'result' => $result,
        ]);
    }

    private function finishRecord(GenerationStatusRecord $record): void
    {
        $now = Db::prepareDateForDb(new \DateTimeImmutable());
        $record->dateFinished = $now;
        $record->dateUpdated = $now;
        $record->save(false);
    }
}
