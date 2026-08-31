<?php
/**
 * Generate the historical-status backup fixture through its exact producer.
 *
 * This executable support file intentionally lives outside the analyzed test
 * tree because it loads a historical class with the current package namespace.
 */

declare(strict_types=1);

use craft\helpers\Json;

$historicalRoot = $argv[1] ?? null;
$outputRoot = $argv[2] ?? null;
$producerCommit = '1cf468858d68862925115febf531fdcb7fa07844';

if (!is_string($historicalRoot) || !is_string($outputRoot)) {
    fwrite(STDERR, "Usage: generate-historical-backup-fixture.php HISTORICAL_ROOT OUTPUT_ROOT\n");
    exit(2);
}

$workspaceRoot = dirname(__DIR__, 3);
require $workspaceRoot . '/vendor/autoload.php';
require $workspaceRoot . '/vendor/yiisoft/yii2/Yii.php';
require $workspaceRoot . '/vendor/craftcms/cms/src/Craft.php';

Craft::$app = new class([
    'id' => 'historical-backup-fixture',
    'basePath' => $outputRoot,
]) extends yii\console\Application {
    public function getConfig(): object
    {
        return new class {
            public function getGeneral(): object
            {
                return (object)[
                    'defaultDirMode' => 0775,
                    'defaultFileMode' => 0664,
                    'useFileLocks' => false,
                ];
            }
        };
    }
};

$composerPath = $historicalRoot . '/composer.json';
$composer = Json::decode((string)file_get_contents($composerPath));
if (($composer['version'] ?? null) !== '5.21.3') {
    throw new RuntimeException('Historical producer package version is not 5.21.3.');
}

if (class_exists('lindemannrock\\translationmanager\\TranslationManager', false)) {
    throw new RuntimeException('Current TranslationManager was loaded before the historical producer stub.');
}

final class HistoricalTranslationManagerStub
{
    public static object $fixtureInstance;

    public static function getInstance(): object
    {
        return self::$fixtureInstance;
    }
}
class_alias(HistoricalTranslationManagerStub::class, 'lindemannrock\\translationmanager\\TranslationManager');

$settings = new class($outputRoot) {
    public ?string $backupVolumeUid = null;

    public function __construct(public string $backupPath)
    {
    }

    public function getExportPath(): string
    {
        return $this->backupPath . '/generated-source';
    }
};
$plugin = new class($settings) {
    public function __construct(private readonly object $settings)
    {
    }

    public function getSettings(): object
    {
        return $this->settings;
    }

    public function getAllowedSites(): array
    {
        return [];
    }
};
HistoricalTranslationManagerStub::$fixtureInstance = $plugin;

Craft::setAlias('@historicalBackupFixture', $outputRoot);
$settings->backupPath = '@historicalBackupFixture';
require $historicalRoot . '/src/services/BackupService.php';

$timestamp = '2026-02-26 18:52:11';
$formieSource = 'Historical form label';
$siteSource = 'Historical site label';
$formieRows = [[
    'id' => 41,
    'source' => $formieSource,
    'sourceHash' => md5($formieSource),
    'context' => 'formie.form.fixture.label',
    'category' => 'formie',
    'siteId' => 1,
    'language' => 'en',
    'translationKey' => $formieSource,
    'translation' => 'Approved historical form label',
    'status' => 'approved',
    'usageCount' => 7,
    'lastUsed' => $timestamp,
    'dateCreated' => $timestamp,
    'dateUpdated' => $timestamp,
    'uid' => '1cf46885-8d68-4629-a15f-ebf531fdcb7f',
]];
$siteRows = [[
    'id' => 42,
    'source' => $siteSource,
    'sourceHash' => md5($siteSource),
    'context' => 'site.fixture',
    'category' => 'messages',
    'siteId' => 1,
    'language' => 'en',
    'translationKey' => $siteSource,
    'translation' => 'AI draft historical site label',
    'status' => 'ai_draft',
    'usageCount' => 3,
    'lastUsed' => $timestamp,
    'dateCreated' => $timestamp,
    'dateUpdated' => $timestamp,
    'uid' => '0683aeea-5ba5-48ac-a6da-d4b345de2177',
]];
$metadata = [
    'date' => '2026-02-26_22-52-11',
    'timestamp' => 1_772_131_931,
    'reason' => 'manual',
    'user' => 'synthetic-fixture',
    'userId' => null,
    'translationCount' => 2,
    'formieEnabled' => true,
    'siteEnabled' => true,
    'craftVersion' => Composer\InstalledVersions::getPrettyVersion('craftcms/cms'),
    'pluginVersion' => $composer['version'],
];

$class = new ReflectionClass('lindemannrock\\translationmanager\\services\\BackupService');
$service = $class->newInstanceWithoutConstructor();
$writer = $class->getMethod('_createLocalBackup');
$backupPath = $writer->invoke(
    $service,
    'product-generated-historical-statuses',
    $metadata,
    $formieRows,
    $siteRows,
);
if (!is_string($backupPath)) {
    throw new RuntimeException('Historical producer did not return a backup path.');
}

$metadataContent = file_get_contents($backupPath . '/metadata.json');
$formieContent = file_get_contents($backupPath . '/formie-translations.json');
$siteContent = file_get_contents($backupPath . '/site-translations.json');
if (!is_string($metadataContent) || !is_string($formieContent) || !is_string($siteContent)) {
    throw new RuntimeException('Historical producer did not write the complete fixture.');
}
$writtenMetadata = Json::decode($metadataContent);
$calculatedChecksum = hash('sha256', $formieContent . $siteContent);
if (($writtenMetadata['checksum'] ?? null) !== $calculatedChecksum) {
    throw new RuntimeException('Historical producer checksum verification failed.');
}

fwrite(STDOUT, Json::encode([
    'producerCommit' => $producerCommit,
    'packageVersion' => $composer['version'],
    'method' => 'BackupService::_createLocalBackup',
    'checksum' => $calculatedChecksum,
    'backupPath' => $backupPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
