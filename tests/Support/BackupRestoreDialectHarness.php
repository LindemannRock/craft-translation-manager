<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

use craft\db\Connection;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\BackupService;
use lindemannrock\translationmanager\services\GenerationService;
use lindemannrock\translationmanager\TranslationManager;

$workspaceRoot = dirname(__DIR__, 4);
require $workspaceRoot . '/bootstrap.php';
/** @var craft\console\Application $app */
$app = require $workspaceRoot . '/vendor/craftcms/cms/bootstrap/console.php';
$app->init();

/**
 * Records whether generation is reached only after the database transaction.
 *
 * @since 5.35.0
 */
final class DialectRestoreGenerationSpy extends GenerationService
{
    public int $generateCalls = 0;
    public ?bool $transactionActive = null;

    public function __construct(private readonly string $outputPath)
    {
        parent::__construct();
    }

    public function generateAll(): array
    {
        $this->generateCalls++;
        $this->transactionActive = Craft::$app->getDb()->getTransaction() !== null;
        if (file_put_contents($this->outputPath, 'generated after committed restore') === false) {
            throw new RuntimeException('Unable to write the owned generated-output fixture.');
        }
        return ['success' => true, 'results' => []];
    }
}

/**
 * Returns a deterministic false persistence result after the first insert.
 *
 * @since 5.35.0
 */
final class DialectFailingRestoreBackupService extends BackupService
{
    private int $persistenceCalls = 0;

    protected function persistRestoreRecord(TranslationRecord $translation): bool
    {
        $this->persistenceCalls++;
        return $this->persistenceCalls === 2 ? false : parent::persistRestoreRecord($translation);
    }
}

$driver = $argv[1] ?? '';
$dsn = App::env('TM_RESTORE_DSN');
$username = App::env('TM_RESTORE_USERNAME');
$password = App::env('TM_RESTORE_PASSWORD');
if (!in_array($driver, ['mysql', 'pgsql'], true)
    || !is_string($dsn)
    || !is_string($username)
    || !is_string($password)) {
    fwrite(STDERR, "Usage: BackupRestoreDialectHarness.php mysql|pgsql with TM_RESTORE_* credentials.\n");
    exit(2);
}

$plugin = TranslationManager::getInstance();
$originalDb = Craft::$app->getDb();
$originalBackup = $plugin->get('backup');
$originalGeneration = $plugin->get('generate');
$settings = $plugin->getSettings();
$settingsSnapshot = $settings->getAttributes();
$db = new Connection([
    'dsn' => $dsn,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8',
    'tablePrefix' => 'tm_restore_',
]);
$table = TranslationRecord::tableName();
$backupDirectory = 'tm-restore-dialect-' . $driver . '-' . bin2hex(random_bytes(8));
$backupRoot = Craft::getAlias('@storage/translation-manager/' . $backupDirectory);
$generatedOutput = $backupRoot . '-generated.php';
$assertions = 0;
$assert = static function(bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$cleanup = static function() use ($db, $table, $backupRoot, $generatedOutput): void {
    if ($db->getTableSchema($table, true) !== null) {
        (new class(['db' => $db]) extends Migration {
        })->dropTable($table);
    }
    if (is_dir($backupRoot)) {
        FileHelper::removeDirectory($backupRoot);
    }
    if (is_file($generatedOutput)) {
        unlink($generatedOutput);
    }
};
$seedCurrentCatalogue = static function(string $suffix) use ($db, $table): void {
    $db->createCommand()->delete($table)->execute();
    $source = '__tm_restore_dialect_current_' . $suffix;
    $now = Db::prepareDateForDb(DateTimeHelper::toDateTime('2026-08-20 12:00:00'));
    $db->createCommand()->insert($table, [
        'source' => $source,
        'sourceHash' => md5($source),
        'context' => 'site.restore-dialect',
        'category' => 'messages',
        'siteId' => 1,
        'language' => 'en',
        'translationKey' => $source,
        'translation' => 'current catalogue',
        'status' => 'translated',
        'translationOrigin' => 'system',
        'usageCount' => 1,
        'lastUsed' => $now,
        'dateCreated' => $now,
        'dateUpdated' => $now,
        'uid' => StringHelper::UUID(),
    ])->execute();
};
$catalogueBytes = static fn(): string => Json::encode((new Query())
    ->from($table)
    ->orderBy(['id' => SORT_ASC])
    ->all($db));
$semanticRow = static fn(array $row): array => [
    'source' => $row['source'],
    'sourceHash' => $row['sourceHash'],
    'context' => $row['context'],
    'category' => $row['category'],
    'siteId' => $row['siteId'],
    'language' => $row['language'],
    'translationKey' => $row['translationKey'],
    'translation' => $row['translation'],
    'status' => $row['status'],
    'translationOrigin' => $row['translationOrigin'],
    'createdByUserId' => $row['createdByUserId'],
    'reviewedByUserId' => $row['reviewedByUserId'],
    'reviewedAt' => $row['reviewedAt'],
    'usageCount' => $row['usageCount'],
    'lastUsed' => $row['lastUsed'],
    'dateCreated' => $row['dateCreated'],
    'dateUpdated' => $row['dateUpdated'],
    'uid' => $row['uid'],
];
$catalogueSemantics = static fn() => array_map(
    $semanticRow,
    (new Query())->from($table)->orderBy(['source' => SORT_ASC])->all($db),
);
$writeBackup = static function(string $name, array $files) use ($backupRoot): void {
    $root = $backupRoot . '/' . $name;
    FileHelper::createDirectory($root);
    foreach ($files as $relativePath => $content) {
        if (file_put_contents($root . '/' . $relativePath, $content) === false) {
            throw new RuntimeException("Unable to write dialect fixture {$relativePath}.");
        }
    }
};
$fixtureRoot = dirname(__DIR__) . '/Fixtures/Backups/product-generated-historical-statuses';
$historicalFiles = [];
foreach (['metadata.json', 'formie-translations.json', 'site-translations.json'] as $file) {
    $content = file_get_contents($fixtureRoot . '/' . $file);
    if (!is_string($content)) {
        throw new RuntimeException("Unable to read historical dialect fixture {$file}.");
    }
    $historicalFiles[$file] = $content;
}
$historicalMetadata = Json::decode($historicalFiles['metadata.json']);
$historicalRows = [
    ...Json::decode($historicalFiles['formie-translations.json']),
    ...Json::decode($historicalFiles['site-translations.json']),
];
$historicalExpected = [];
foreach ($historicalRows as $row) {
    $row['status'] = $row['status'] === 'approved' ? 'translated' : 'draft';
    $row['translationOrigin'] = 'system';
    $row['createdByUserId'] = null;
    $row['reviewedByUserId'] = null;
    $row['reviewedAt'] = null;
    $historicalExpected[] = $semanticRow($row);
}
$semanticTimestamp = Db::prepareDateForDb(DateTimeHelper::toDateTime('2026-08-21 13:14:15'));
$currentRows = [
    [
        'source' => '__tm_restore_dialect_semantic_ai',
        'sourceHash' => md5('__tm_restore_dialect_semantic_ai'),
        'context' => 'site.restore-dialect',
        'category' => 'messages',
        'siteId' => 1,
        'language' => 'en',
        'translationKey' => '__tm_restore_dialect_semantic_ai',
        'translation' => 'AI semantic translation',
        'status' => 'draft',
        'translationOrigin' => 'ai',
        'createdByUserId' => 72_001,
        'reviewedByUserId' => 72_002,
        'reviewedAt' => $semanticTimestamp,
        'usageCount' => 8,
        'lastUsed' => $semanticTimestamp,
        'dateCreated' => $semanticTimestamp,
        'dateUpdated' => $semanticTimestamp,
        'uid' => '72000000-0000-4000-8000-000000000001',
    ],
    [
        'source' => '__tm_restore_dialect_semantic_manual',
        'sourceHash' => md5('__tm_restore_dialect_semantic_manual'),
        'context' => 'site.restore-dialect',
        'category' => 'messages',
        'siteId' => 1,
        'language' => 'en',
        'translationKey' => '__tm_restore_dialect_semantic_manual',
        'translation' => 'Manual semantic translation',
        'status' => 'translated',
        'translationOrigin' => 'manual',
        'createdByUserId' => 71_001,
        'reviewedByUserId' => 71_002,
        'reviewedAt' => $semanticTimestamp,
        'usageCount' => 9,
        'lastUsed' => $semanticTimestamp,
        'dateCreated' => $semanticTimestamp,
        'dateUpdated' => $semanticTimestamp,
        'uid' => '71000000-0000-4000-8000-000000000001',
    ],
];
$currentContent = Json::encode($currentRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$currentFiles = [
    'metadata.json' => Json::encode([
        'translationCount' => count($currentRows),
        'checksum' => hash('sha256', $currentContent),
        'checksumAlgorithm' => 'sha256',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    'site-translations.json' => $currentContent,
];

try {
    $db->open();
    $migration = new class(['db' => $db]) extends Migration {
    };
    $cleanup();
    $migration->createTable($table, [
        'id' => $migration->primaryKey(),
        'source' => $migration->text()->notNull(),
        'sourceHash' => $migration->string(32)->notNull(),
        'context' => $migration->string(255)->notNull(),
        'category' => $migration->string(50)->notNull()->defaultValue('messages'),
        'siteId' => $migration->integer()->notNull()->defaultValue(1),
        'language' => $migration->string(12)->notNull(),
        'translationKey' => $migration->text()->notNull(),
        'translation' => $migration->text()->null(),
        'status' => $migration->string(20)->notNull()->defaultValue('pending'),
        'translationOrigin' => $migration->string(20)->notNull()->defaultValue('system'),
        'createdByUserId' => $migration->integer()->null(),
        'reviewedByUserId' => $migration->integer()->null(),
        'reviewedAt' => $migration->dateTime()->null(),
        'usageCount' => $migration->integer()->notNull()->defaultValue(1),
        'lastUsed' => $migration->dateTime()->null(),
        'dateCreated' => $migration->dateTime()->notNull(),
        'dateUpdated' => $migration->dateTime()->notNull(),
        'uid' => $migration->string(36)->notNull(),
    ]);
    $migration->createIndex(null, $table, ['sourceHash', 'language', 'category'], true);

    Craft::$app->set('db', $db);
    $settings->backupEnabled = false;
    $settings->backupVolumeUid = null;
    $settings->backupPath = '@storage/translation-manager/' . $backupDirectory;
    $assert(file_put_contents($generatedOutput, 'generated before restore') !== false, 'Owned generated-output fixture could not be created.');
    $generation = new DialectRestoreGenerationSpy($generatedOutput);
    $plugin->set('generate', $generation);
    $service = new BackupService();
    $plugin->set('backup', $service);

    $successName = 'manual/2026-08-20_12-00-00';
    $seedCurrentCatalogue('success');
    $writeBackup($successName, $historicalFiles);
    $success = $service->restoreBackup($successName);
    $assert($success['success'] === true, 'Historical restore did not succeed: ' . ($success['message'] ?? 'no message'));
    $assert($success['imported'] === 2, 'Historical restore did not insert both rows.');
    $assert($historicalMetadata['pluginVersion'] === '5.21.3', 'Historical fixture package provenance changed.');
    $assert(
        $historicalMetadata['checksum'] === hash('sha256', $historicalFiles['formie-translations.json'] . $historicalFiles['site-translations.json']),
        'Historical fixture checksum is invalid.',
    );
    $assert($catalogueSemantics() === $historicalExpected, 'Historical restore did not preserve complete compatible semantics.');
    $assert($generation->generateCalls === 1, 'Successful restore did not generate exactly once.');
    $assert($generation->transactionActive === false, 'Generation ran before the restore transaction committed.');
    $assert(file_get_contents($generatedOutput) === 'generated after committed restore', 'Successful restore did not refresh generated output.');

    $currentName = 'manual/2026-08-20_12-00-03';
    $seedCurrentCatalogue('current-semantics');
    $writeBackup($currentName, $currentFiles);
    $current = $service->restoreBackup($currentName);
    $assert($current['success'] === true, 'Current-format semantic restore did not succeed.');
    $assert($current['imported'] === 2, 'Current-format semantic restore did not insert both rows.');
    $assert($catalogueSemantics() === array_map($semanticRow, $currentRows), 'Current-format semantic fields changed during restore.');
    $assert($generation->generateCalls === 2, 'Current-format semantic restore did not generate exactly once.');
    $assert($generation->transactionActive === false, 'Current-format generation ran before commit.');
    $generatedHash = hash_file('sha256', $generatedOutput);

    $invalidName = 'manual/2026-08-20_12-00-01';
    $seedCurrentCatalogue('invalid');
    $invalidBefore = $catalogueBytes();
    $invalidRows = Json::decode($historicalFiles['site-translations.json']);
    $invalidRows[0]['sourceHash'] = str_repeat('x', 33);
    $invalidSite = Json::encode($invalidRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $invalidFiles = [
        'metadata.json' => Json::encode([
            'checksum' => hash('sha256', $invalidSite),
            'checksumAlgorithm' => 'sha256',
        ]),
        'site-translations.json' => $invalidSite,
    ];
    $writeBackup($invalidName, $invalidFiles);
    $invalid = $service->restoreBackup($invalidName);
    $assert($invalid['success'] === false, 'Invalid restore reported success.');
    $assert($catalogueBytes() === $invalidBefore, 'Invalid preflight changed the current catalogue.');
    $assert($generation->generateCalls === 2, 'Invalid preflight triggered generation.');
    $assert(hash_file('sha256', $generatedOutput) === $generatedHash, 'Invalid preflight changed generated output.');

    $failureName = 'manual/2026-08-20_12-00-02';
    $seedCurrentCatalogue('persistence');
    $failureBefore = $catalogueBytes();
    $writeBackup($failureName, $historicalFiles);
    $failingService = new DialectFailingRestoreBackupService();
    $plugin->set('backup', $failingService);
    $failure = $failingService->restoreBackup($failureName);
    $assert($failure['success'] === false, 'Injected persistence failure reported success.');
    $assert($failure['imported'] === 0, 'Rolled-back persistence failure reported imported rows.');
    $assert($catalogueBytes() === $failureBefore, 'Mid-insert failure did not roll back byte-identically.');
    $assert($generation->generateCalls === 2, 'Mid-insert failure triggered generation.');
    $assert(hash_file('sha256', $generatedOutput) === $generatedHash, 'Mid-insert failure changed generated output.');
    $assert($db->getTransaction() === null, 'Restore left a transaction active.');
    $assert($db->getDriverName() === $driver, 'Harness used the wrong database driver.');

    Craft::$app->set('db', $originalDb);
    $cleanup();
    $assert($db->getTableSchema($table, true) === null, 'Disposable restore table remained after cleanup.');
    $assert(!is_dir($backupRoot), 'Disposable restore backup directory remained after cleanup.');
    $assert(!is_file($generatedOutput), 'Disposable generated-output fixture remained after cleanup.');
    fwrite(STDOUT, "{$driver}: {$assertions} restore assertions passed; disposable table and files clean.\n");
} catch (Throwable $exception) {
    Craft::$app->set('db', $originalDb);
    try {
        $cleanup();
    } catch (Throwable $cleanupException) {
        fwrite(STDERR, "Cleanup failure: {$cleanupException->getMessage()}\n");
    }
    fwrite(STDERR, "{$driver} restore harness failed after {$assertions} assertions: {$exception->getMessage()}\n");
    exit(1);
} finally {
    Craft::$app->set('db', $originalDb);
    $plugin->set('backup', $originalBackup);
    $plugin->set('generate', $originalGeneration);
    $settings->setAttributes($settingsSnapshot, false);
    $db->close();
}
