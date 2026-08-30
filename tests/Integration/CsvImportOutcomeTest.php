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
use craft\console\Request as ConsoleRequest;
use craft\console\User as ConsoleUser;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\web\Response;
use craft\web\Session;
use lindemannrock\translationmanager\controllers\ImportController;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins CSV preview, persistence, history, feedback, generation, and retry outcomes.
 *
 * @since 5.35.0
 */
final class CsvImportOutcomeTest extends TestCase
{
    /** @var list<string> */
    private array $historyFilenames = [];
    private CsvOutcomeGenerationService $generationSpy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings()->backupEnabled = false;
        $this->settings()->backupOnImport = false;
        $this->generationSpy = new CsvOutcomeGenerationService();
        $this->replacePluginComponent('generate', $this->generationSpy);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->historyFilenames as $filename) {
                ImportHistoryRecord::deleteAll(['filename' => $filename]);
            }
            $this->historyFilenames = [];
        } finally {
            parent::tearDown();
        }
    }

    public function testMixedValidAndInvalidRowsReportPartialSuccessAndGenerateAfterWrites(): void
    {
        $source = self::MARKER . 'csv_mixed_' . bin2hex(random_bytes(4));
        $preview = $this->analyze([
            $this->row($source, 'Written value', 'site', 2),
            $this->row(self::MARKER . 'csv_mixed_invalid_' . bin2hex(random_bytes(4)), 'Rejected value', str_repeat('x', 256), 3),
        ]);

        self::assertCount(1, $preview['toImport']);
        self::assertCount(1, $preview['errors']);

        $filename = $this->ownedFilename('mixed');
        $session = $this->runAction($filename, $preview);
        $history = $this->history($filename);
        $details = $this->historyDetails($history);
        $errors = $this->historyErrors($history);

        self::assertCount(1, $this->fetchRowsForSource($source));
        self::assertSame(1, (int)$history->imported);
        self::assertSame(0, (int)$history->updated);
        self::assertSame(0, (int)$history->skipped);
        self::assertSame(1, $details['failed']);
        self::assertSame(1, $details['errorCount']);
        self::assertSame(1, $details['retainedErrorCount']);
        self::assertCount(1, $errors);
        self::assertStringContainsString('255', $errors[0]);
        self::assertSame(1, $this->generationSpy->calls);
        self::assertSame('Saved 1 translation(s), 1 failed.', $session->getError());
        self::assertNull($session->getNotice());
        self::assertFalse($session->has('translation-import'));
        self::assertFalse($session->has('translation-preview'));
    }

    public function testAllInvalidRowsReportFailureWithoutGeneration(): void
    {
        $sources = [];
        $rows = [];
        for ($index = 0; $index < 12; $index++) {
            $sources[] = self::MARKER . 'csv_invalid_' . $index . '_' . bin2hex(random_bytes(4));
            $rows[] = $this->row($sources[$index], 'Rejected value', str_repeat('x', 256), $index + 2);
        }
        $preview = $this->analyze($rows);

        self::assertSame([], $preview['toImport']);
        self::assertCount(12, $preview['errors']);

        $filename = $this->ownedFilename('invalid');
        $session = $this->runAction($filename, $preview);
        $history = $this->history($filename);
        $details = $this->historyDetails($history);

        foreach ($sources as $source) {
            self::assertSame([], $this->fetchRowsForSource($source));
        }
        self::assertSame(0, (int)$history->imported);
        self::assertSame(0, (int)$history->updated);
        self::assertSame(0, (int)$history->skipped);
        self::assertSame(12, $details['failed']);
        self::assertSame(12, $details['errorCount']);
        self::assertSame(10, $details['retainedErrorCount']);
        self::assertCount(10, $this->historyErrors($history));
        self::assertSame(0, $this->generationSpy->calls);
        self::assertSame('Saved 0 translation(s), 12 failed.', $session->getError());
        self::assertNull($session->getNotice());
    }

    public function testRetryUpdatesFailedRowWithoutDuplicatingSuccessfulRow(): void
    {
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $category = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $updateSource = self::MARKER . 'csv_retry_update_' . bin2hex(random_bytes(4));
        $newSource = self::MARKER . 'csv_retry_new_' . bin2hex(random_bytes(4));
        $this->createTranslationRecord($updateSource, 'Before retry', $language, $category);

        $preview = $this->analyze([
            $this->row($updateSource, 'After retry', 'site', 2),
            $this->row($newSource, 'Written once', 'site', 3),
        ]);
        self::assertCount(1, $preview['toUpdate']);
        self::assertCount(1, $preview['toImport']);

        $failingService = new CsvOutcomeTranslationsService();
        $failingService->failedSources = [$updateSource];
        $this->replacePluginComponent('translations', $failingService);

        $firstFilename = $this->ownedFilename('retry-first');
        $firstSession = $this->runAction($firstFilename, $preview);
        $firstHistory = $this->history($firstFilename);
        $firstDetails = $this->historyDetails($firstHistory);

        self::assertSame(1, (int)$firstHistory->imported);
        self::assertSame(0, (int)$firstHistory->updated);
        self::assertSame(1, $firstDetails['failed']);
        self::assertSame(1, $firstDetails['errorCount']);
        self::assertStringContainsString('Saved 1 translation(s), 1 failed.', (string)$firstSession->getError());
        self::assertSame('Before retry', $this->fetchRowsForSource($updateSource)[0]['translation']);
        self::assertCount(1, $this->fetchRowsForSource($newSource));

        $this->replacePluginComponent('translations', $this->translations);
        $retryFilename = $this->ownedFilename('retry-second');
        $retrySession = $this->runAction($retryFilename, $preview);
        $retryHistory = $this->history($retryFilename);
        $retryDetails = $this->historyDetails($retryHistory);

        self::assertSame(0, (int)$retryHistory->imported);
        self::assertSame(1, (int)$retryHistory->updated);
        self::assertSame(1, (int)$retryHistory->skipped);
        self::assertSame(0, $retryDetails['failed']);
        self::assertSame(0, $retryDetails['errorCount']);
        self::assertNull($retrySession->getError());
        self::assertNotNull($retrySession->getNotice());
        self::assertSame('After retry', $this->fetchRowsForSource($updateSource)[0]['translation']);
        self::assertCount(1, $this->fetchRowsForSource($newSource));
        self::assertSame(2, $this->generationSpy->calls);
    }

    public function testValidRowsRetainSuccessFeedbackAndGeneration(): void
    {
        $source = self::MARKER . 'csv_success_' . bin2hex(random_bytes(4));
        $preview = $this->analyze([
            $this->row($source, 'Successful value', 'site', 2),
        ]);
        $filename = $this->ownedFilename('success');

        $session = $this->runAction($filename, $preview);
        $history = $this->history($filename);
        $details = $this->historyDetails($history);

        self::assertSame(1, (int)$history->imported);
        self::assertSame(0, $details['failed']);
        self::assertSame(0, $details['errorCount']);
        self::assertSame(1, $this->generationSpy->calls);
        self::assertNull($session->getError());
        self::assertSame('Successfully imported 1 translations.', $session->getNotice());
    }

    public function testFinalValidationPreservesTotalErrorsBeyondRetainedDetails(): void
    {
        $rows = [];
        for ($index = 0; $index < 12; $index++) {
            $rows[] = $this->row(
                self::MARKER . 'csv_error_cap_' . $index . '_' . bin2hex(random_bytes(4)),
                'Rejected value',
                str_repeat('x', 256),
                $index + 2,
            );
        }

        $results = $this->invokeImportTranslations($rows);

        self::assertSame(0, $results['imported']);
        self::assertSame(0, $results['updated']);
        self::assertSame(0, $results['skipped']);
        self::assertSame(12, $results['failed']);
        self::assertSame(12, $results['errorCount']);
        self::assertCount(10, $results['errors']);
        self::assertSame(0, $this->generationSpy->calls);
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function analyze(array $rows): array
    {
        $controller = new ImportController('import', TranslationManager::getInstance());
        $method = new \ReflectionMethod($controller, 'analyzeTranslations');
        $method->setAccessible(true);

        /** @var array<string,mixed> $analysis */
        $analysis = $method->invoke($controller, $rows);
        return $analysis;
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function invokeImportTranslations(array $rows): array
    {
        $controller = new ImportController('import', TranslationManager::getInstance());
        $method = new \ReflectionMethod($controller, 'importTranslations');
        $method->setAccessible(true);

        /** @var array<string,mixed> $results */
        $results = $method->invoke($controller, $rows, false);
        return $results;
    }

    /** @param array<string,mixed> $preview */
    private function runAction(string $filename, array $preview): CsvOutcomeSession
    {
        $admin = Craft::$app->getUsers()->getUserById(1);
        self::assertNotNull($admin);

        $session = new CsvOutcomeSession();
        $session->set('translation-import', [
            'allRows' => [['owned']],
            'filename' => $filename,
            'filesize' => 128,
            'createBackup' => false,
        ]);
        $session->set('translation-preview', [
            'summary' => [
                'totalRows' => count($preview['toImport']) + count($preview['toUpdate']) + count($preview['unchanged']) + count($preview['malicious']) + count($preview['errors']),
                'toImport' => count($preview['toImport']),
                'toUpdate' => count($preview['toUpdate']),
                'unchanged' => count($preview['unchanged']),
                'malicious' => count($preview['malicious']),
                'errors' => count($preview['errors']),
            ],
            'toImport' => $preview['toImport'],
            'toUpdate' => $preview['toUpdate'],
            'unchanged' => $preview['unchanged'],
            'malicious' => $preview['malicious'],
            'errors' => $preview['errors'],
            'createBackup' => false,
        ]);

        $originalUser = Craft::$app->getUser();
        $originalRequest = Craft::$app->getRequest();
        $originalResponse = Craft::$app->getResponse();
        $user = new CsvOutcomeUser();
        $user->setIdentity($admin);

        try {
            Craft::$app->set('user', $user);
            Craft::$app->set('request', new CsvOutcomeRequest());
            Craft::$app->set('response', new Response());

            $response = (new CsvOutcomeImportController('import', TranslationManager::getInstance(), $session))->actionIndex();
            self::assertSame(302, $response->getStatusCode());
        } finally {
            Craft::$app->set('user', $originalUser);
            Craft::$app->set('request', $originalRequest);
            Craft::$app->set('response', $originalResponse);
        }

        return $session;
    }

    /** @return array<string,mixed> */
    private function row(string $source, string $translation, string $context, int $rowNumber): array
    {
        return [
            'translationKey' => $source,
            'translation' => $translation,
            'language' => Craft::$app->getSites()->getPrimarySite()->language,
            'category' => TranslationManager::getInstance()->getSettings()->getPrimaryCategory(),
            'context' => $context,
            'rowNumber' => $rowNumber,
            '_rowNumber' => $rowNumber,
        ];
    }

    private function ownedFilename(string $label): string
    {
        $filename = self::MARKER . $label . '_' . bin2hex(random_bytes(4)) . '.csv';
        $this->historyFilenames[] = $filename;
        return $filename;
    }

    private function history(string $filename): ImportHistoryRecord
    {
        $history = ImportHistoryRecord::findOne(['filename' => $filename]);
        self::assertInstanceOf(ImportHistoryRecord::class, $history);
        return $history;
    }

    /** @return array<string,int> */
    private function historyDetails(ImportHistoryRecord $history): array
    {
        $details = json_decode((string)$history->details, true);
        self::assertIsArray($details);
        return $details;
    }

    /** @return list<string> */
    private function historyErrors(ImportHistoryRecord $history): array
    {
        $errors = json_decode((string)$history->errors, true);
        self::assertIsArray($errors);
        return array_values(array_map('strval', $errors));
    }

    private function createTranslationRecord(string $source, string $translation, string $language, string $category): void
    {
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->translationKey = $source;
        $record->translation = $translation;
        $record->language = TranslationManager::getInstance()->getSettings()->mapLanguage($language);
        $record->category = $category;
        $record->context = 'site';
        $record->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        $record->lastUsed = Db::prepareDateForDb(new \DateTime());
        $record->dateCreated = Db::prepareDateForDb(new \DateTime());
        $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
        $record->uid = StringHelper::UUID();

        self::assertTrue($record->save(), json_encode($record->getErrors()));
    }
}

/** @since 5.35.0 */
final class CsvOutcomeImportController extends ImportController
{
    public function __construct(string $id, $module, private readonly CsvOutcomeSession $outcomeSession, array $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function getImportSession(): Session
    {
        return $this->outcomeSession;
    }
}

/** @since 5.35.0 */
final class CsvOutcomeSession extends Session
{
    /** @var array<string,mixed> */
    private array $values = [];
    /** @var array<string,string> */
    private array $flashes = [];

    public function get($key, $defaultValue = null)
    {
        return $this->values[(string)$key] ?? $defaultValue;
    }

    public function set($key, $value): void
    {
        $this->values[(string)$key] = $value;
    }

    public function remove($key)
    {
        $key = (string)$key;
        $value = $this->values[$key] ?? null;
        unset($this->values[$key]);
        return $value;
    }

    public function has($key): bool
    {
        return array_key_exists((string)$key, $this->values);
    }

    public function setNotice(string $message, array $settings = []): void
    {
        $this->flashes['notice'] = $message;
    }

    public function setError(string $message, array $settings = []): void
    {
        $this->flashes['error'] = $message;
    }

    public function getNotice(): ?string
    {
        return $this->flashes['notice'] ?? null;
    }

    public function getError(): ?string
    {
        return $this->flashes['error'] ?? null;
    }
}

/** @since 5.35.0 */
final class CsvOutcomeUser extends ConsoleUser
{
    public function checkPermission(string $permissionName): bool
    {
        return true;
    }
}

/** @since 5.35.0 */
final class CsvOutcomeRequest extends ConsoleRequest
{
    public function getIsPost(): bool
    {
        return true;
    }

    public function getIsAjax(): bool
    {
        return false;
    }
}

/** @since 5.35.0 */
final class CsvOutcomeGenerationService extends GenerationService
{
    public int $calls = 0;

    public function triggerAutoGenerate(?array $sourceIds = null): bool
    {
        $this->calls++;
        return true;
    }
}

/** @since 5.35.0 */
final class CsvOutcomeTranslationsService extends TranslationsService
{
    /** @var list<string> */
    public array $failedSources = [];

    public function saveTranslation(TranslationRecord $translation): bool
    {
        if (in_array($translation->source, $this->failedSources, true)) {
            $translation->addError('translation', 'Injected final save failure');
            return false;
        }

        return parent::saveTranslation($translation);
    }
}
