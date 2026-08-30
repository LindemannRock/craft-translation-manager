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
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;

$workspaceRoot = dirname(__DIR__, 4);
require $workspaceRoot . '/bootstrap.php';
/** @var craft\console\Application $app */
$app = require $workspaceRoot . '/vendor/craftcms/cms/bootstrap/console.php';
$app->init();

$driver = $argv[1] ?? '';
$dsn = App::env('TM_PAGINATION_DSN');
$username = App::env('TM_PAGINATION_USERNAME');
$password = App::env('TM_PAGINATION_PASSWORD');
if (!in_array($driver, ['mysql', 'pgsql'], true)
    || !is_string($dsn)
    || !is_string($username)
    || !is_string($password)) {
    fwrite(STDERR, "Usage: TranslationsPaginationDialectHarness.php mysql|pgsql with TM_PAGINATION_* credentials.\n");
    exit(2);
}

$originalDb = Craft::$app->getDb();
$settings = TranslationManager::getInstance()->getSettings();
$settings->getActiveLocaleMapping();
$service = TranslationManager::getInstance()->translations;
$db = new Connection([
    'dsn' => $dsn,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8',
    'tablePrefix' => 'tm_page_',
]);
$table = TranslationRecord::tableName();
$assertions = 0;
$assert = static function(bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$cleanup = static function() use ($db, $table): void {
    if ($db->getTableSchema($table, true) !== null) {
        (new class(['db' => $db]) extends Migration {
        })->dropTable($table);
    }
};

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
        'category' => $migration->string(50)->notNull(),
        'siteId' => $migration->integer()->notNull(),
        'language' => $migration->string(12)->notNull(),
        'translationKey' => $migration->text()->notNull(),
        'translation' => $migration->text()->null(),
        'status' => $migration->string(20)->notNull(),
        'translationOrigin' => $migration->string(20)->notNull(),
        'createdByUserId' => $migration->integer()->null(),
        'reviewedByUserId' => $migration->integer()->null(),
        'reviewedAt' => $migration->dateTime()->null(),
        'usageCount' => $migration->integer()->notNull(),
        'lastUsed' => $migration->dateTime()->null(),
        'dateCreated' => $migration->dateTime()->notNull(),
        'dateUpdated' => $migration->dateTime()->notNull(),
        'uid' => $migration->string(36)->notNull(),
    ]);

    $now = Db::prepareDateForDb(new DateTime());
    $rows = [];
    for ($index = 0; $index < 31; $index++) {
        $source = sprintf('__tm_page_source_%03d', $index);
        $rows[] = [
            $source,
            md5($source),
            'site.pagination',
            'messages',
            1,
            'en',
            sprintf('__tm_page_key_%03d', $index),
            sprintf('__tm_page_translation_%03d', 31 - $index),
            $index % 2 === 0 ? 'translated' : 'draft',
            $index % 3 === 0 ? 'import' : 'manual',
            null,
            null,
            null,
            1,
            $now,
            $now,
            $now,
            StringHelper::UUID(),
        ];
    }
    $db->createCommand()->batchInsert($table, [
        'source', 'sourceHash', 'context', 'category', 'siteId', 'language',
        'translationKey', 'translation', 'status', 'translationOrigin',
        'createdByUserId', 'reviewedByUserId', 'reviewedAt', 'usageCount',
        'lastUsed', 'dateCreated', 'dateUpdated', 'uid',
    ], $rows)->execute();

    Craft::$app->set('db', $db);
    $criteria = [
        'language' => 'en',
        'status' => 'all',
        'search' => '__tm_page_',
        'sort' => 'translation',
        'dir' => 'desc',
        'type' => 'all',
        'origin' => 'all',
        'category' => 'all',
    ];
    $complete = $service->getTranslations($criteria);
    $page = $service->getTranslationsPage($criteria, 10, 10);
    $assert(count($complete) === 31, 'Unbounded query did not return all 31 rows.');
    $assert($page['totalCount'] === 31, 'Filtered database count did not return 31.');
    $assert(count($page['translations']) === 10, 'Bounded query did not return exactly 10 rows.');
    $assert(
        array_column($page['translations'], 'id') === array_column(array_slice($complete, 10, 10), 'id'),
        'Bounded page did not match the logical unbounded result.',
    );
    $assert($db->getDriverName() === $driver, 'Harness used the wrong database driver.');

    Craft::$app->set('db', $originalDb);
    $cleanup();
    $assert($db->getTableSchema($table, true) === null, 'Disposable pagination table remained after cleanup.');
    fwrite(STDOUT, "{$driver}: {$assertions} pagination assertions passed; disposable table clean.\n");
} catch (Throwable $exception) {
    Craft::$app->set('db', $originalDb);
    try {
        $cleanup();
    } catch (Throwable $cleanupException) {
        fwrite(STDERR, "Cleanup failure: {$cleanupException->getMessage()}\n");
    }
    fwrite(STDERR, "{$driver} pagination harness failed after {$assertions} assertions: {$exception->getMessage()}\n");
    exit(1);
} finally {
    Craft::$app->set('db', $originalDb);
    $db->close();
}
