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
use lindemannrock\translationmanager\migrations\Install;
use lindemannrock\translationmanager\migrations\m260828_000000_drop_auto_save_delay;
use yii\db\ColumnSchemaBuilder;
use yii\db\Schema;

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require dirname(__DIR__, 4) . '/vendor/yiisoft/yii2/Yii.php';
require dirname(__DIR__, 4) . '/vendor/craftcms/cms/src/Craft.php';

new yii\console\Application([
    'id' => 'tm-post21-migration-harness',
    'basePath' => dirname(__DIR__, 2),
    'components' => [
        'log' => [
            'targets' => [],
        ],
    ],
]);

$driver = $argv[1] ?? '';
$dsn = App::env('TM_POST21_DSN');
$username = App::env('TM_POST21_USERNAME');
$password = App::env('TM_POST21_PASSWORD');

if (!in_array($driver, ['mysql', 'pgsql'], true)) {
    fwrite(STDERR, "Usage: php AutoSaveDelayMigrationHarness.php mysql|pgsql\n");
    exit(2);
}

if (!is_string($dsn) || !is_string($username) || !is_string($password)) {
    fwrite(STDERR, "TM_POST21_DSN, TM_POST21_USERNAME, and TM_POST21_PASSWORD are required.\n");
    exit(2);
}

$db = new Connection([
    'dsn' => $dsn,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8',
    'tablePrefix' => 'tm_post21_',
]);
Craft::$app->set('db', $db);
$assertions = 0;
$tables = [
    '{{%translationmanager_import_history}}',
    '{{%translationmanager_generation_status}}',
    '{{%translationmanager_settings}}',
    '{{%translationmanager_translations}}',
    '{{%users}}',
];

$assert = static function(bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$schema = static fn() => $db->getTableSchema('{{%translationmanager_settings}}', true);
$cleanup = static function() use ($db, $tables): void {
    $migration = new class(['db' => $db]) extends Migration {
    };
    foreach ($tables as $table) {
        if ($db->getTableSchema($table, true) !== null) {
            $migration->dropTable($table);
        }
    }
};

try {
    $db->open();
    $assert(Craft::$app->getDb() === $db, 'Harness application is not using the disposable database.');
    $assert($db->getSchema()->quoteValue('pending') === "'pending'", 'Disposable database schema cannot quote enum values.');
    $cleanup();

    $migration = new class(['db' => $db]) extends Migration {
    };
    $migration->createTable('{{%users}}', [
        'id' => $migration->primaryKey(),
    ]);

    $install = new class(['db' => $db]) extends Install {
        /** Build the unrelated status enum without requiring a full Craft application. */
        public function enum(string $columnName, array $values): ColumnSchemaBuilder
        {
            $quotedValues = array_map($this->db->quoteValue(...), $values);
            $check = "[[$columnName]] in (" . implode(',', $quotedValues) . ')';

            return $this->string()->check($check);
        }
    };
    $assert($install->safeUp(), 'Fresh install did not complete successfully.');
    $settingsSchema = $schema();
    $assert($settingsSchema !== null, 'Fresh install did not create the settings table.');
    $assert(!isset($settingsSchema->columns['autoSaveDelay']), 'Fresh install retained autoSaveDelay.');

    $assert($install->safeDown(), 'Uninstall did not complete successfully.');
    $assert($schema() === null, 'Uninstall retained the settings table.');

    $assert($install->safeUp(), 'Reinstall did not complete successfully.');
    $settingsSchema = $schema();
    $assert($settingsSchema !== null, 'Reinstall did not recreate the settings table.');
    $assert(!isset($settingsSchema->columns['autoSaveDelay']), 'Reinstall recreated autoSaveDelay.');
    $assert($install->safeDown(), 'Second uninstall did not complete successfully.');

    $migration->createTable('{{%translationmanager_settings}}', [
        'id' => $migration->primaryKey(),
        'autoSaveDelay' => $migration->integer()->notNull()->defaultValue(2),
    ]);
    $db->createCommand()->batchInsert(
        '{{%translationmanager_settings}}',
        ['autoSaveDelay'],
        [[1], [2], [10]],
    )->execute();

    $dropDelay = new m260828_000000_drop_auto_save_delay(['db' => $db]);
    $assert($dropDelay->safeUp(), 'Upgrade migration did not complete successfully.');
    $settingsSchema = $schema();
    $assert($settingsSchema !== null, 'Upgrade unexpectedly removed the settings table.');
    $assert(!isset($settingsSchema->columns['autoSaveDelay']), 'Upgrade retained autoSaveDelay.');
    $ids = (new Query())->from('{{%translationmanager_settings}}')->select(['id'])->column($db);
    $assert(array_map('intval', $ids) === [1, 2, 3], 'Upgrade did not preserve the seeded settings rows.');
    $assert($dropDelay->safeUp(), 'Repeated upgrade was not safe with the column absent.');

    $assert($dropDelay->safeDown(), 'Rollback migration did not complete successfully.');
    $settingsSchema = $schema();
    $assert($settingsSchema !== null, 'Rollback unexpectedly removed the settings table.');
    $column = $settingsSchema->columns['autoSaveDelay'] ?? null;
    $assert($column !== null, 'Rollback did not restore autoSaveDelay.');
    $assert($column->type === Schema::TYPE_INTEGER, 'Rollback did not restore an INTEGER column.');
    $assert($column->allowNull === false, 'Rollback did not restore NOT NULL.');
    $assert((int)$column->defaultValue === 2, 'Rollback did not restore DEFAULT 2.');
    $values = (new Query())
        ->from('{{%translationmanager_settings}}')
        ->select(['autoSaveDelay'])
        ->orderBy(['id' => SORT_ASC])
        ->column($db);
    $assert(
        array_map('intval', $values) === [2, 2, 2],
        'Rollback claimed to recover deleted values instead of restoring the default.',
    );
    $assert($dropDelay->safeDown(), 'Repeated rollback was not safe with the column present.');

    $migration->dropColumn('{{%translationmanager_settings}}', 'autoSaveDelay');
    $assert($dropDelay->safeUp(), 'Upgrade was not safe when the column was already absent.');
    $migration->dropTable('{{%translationmanager_settings}}');
    $assert($dropDelay->safeUp(), 'Upgrade was not safe when the table was absent.');
    $assert($dropDelay->safeDown(), 'Rollback was not safe when the table was absent.');

    $cleanup();
    foreach ($tables as $table) {
        $assert($db->getTableSchema($table, true) === null, "Cleanup retained {$table}.");
    }

    fwrite(STDOUT, "{$driver}: {$assertions} migration assertions passed; disposable schema clean.\n");
} catch (Throwable $exception) {
    try {
        $cleanup();
    } catch (Throwable $cleanupException) {
        fwrite(STDERR, "Cleanup failure: {$cleanupException->getMessage()}\n");
    }
    $messages = [];
    for ($failure = $exception; $failure !== null; $failure = $failure->getPrevious()) {
        $messages[] = $failure::class . ': ' . $failure->getMessage();
    }
    fwrite(STDERR, "{$driver} migration harness failed after {$assertions} assertions: " . implode(' <- ', $messages) . "\n");
    exit(1);
} finally {
    $db->close();
}
