<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

use craft\models\Site;
use lindemannrock\translationmanager\tests\Support\TestProjectBoundary;
use lindemannrock\translationmanager\TranslationManager;

$projectRoot = $_SERVER['TRANSLATION_MANAGER_TEST_PROJECT_ROOT'] ?? null;
if (!is_string($projectRoot)
    || preg_match('#^' . preg_quote(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR), '#')
        . '/translation-manager-fixture-[a-f0-9]{16}$#', $projectRoot) !== 1) {
    throw new RuntimeException('Fixture seeding requires the exact disposable project boundary.');
}
require $projectRoot . '/bootstrap.php';
$boundary = TestProjectBoundary::resolve();
require $boundary->vendorRoot . '/craftcms/cms/bootstrap/console.php';

$sites = Craft::$app->getSites();
$primary = $sites->getPrimarySite();
$created = [];
foreach ([
    ['name' => 'Translation Arabic', 'handle' => 'translationArabic', 'language' => 'ar', 'baseUrl' => 'https://translation-arabic.example.test'],
    ['name' => 'Translation English US', 'handle' => 'translationEnglishUs', 'language' => 'en-US', 'baseUrl' => 'https://translation-english-us.example.test'],
] as $definition) {
    $site = new Site([
        ...$definition,
        'groupId' => $primary->groupId,
        'primary' => false,
        'enabled' => true,
    ]);
    if (!$sites->saveSite($site)) {
        throw new RuntimeException('Unable to save fixture site: ' . json_encode($site->getErrors()));
    }
    $created[] = ['id' => $site->id, 'uid' => $site->uid, 'handle' => $site->handle, 'language' => $site->language];
}

if (count($sites->getAllSites()) !== 3) {
    throw new RuntimeException('Translation Manager fixture must contain exactly three sites.');
}
$languages = array_values(array_unique(array_map(
    static fn(Site $site): string => $site->language,
    $sites->getAllSites(),
)));
sort($languages, SORT_STRING);
if ($languages !== ['ar', 'en', 'en-US']) {
    throw new RuntimeException('Translation Manager fixture must contain the unique languages ar, en, and en-US.');
}

$fixtureSource = 'Translation Manager disposable fixture source';
$translation = TranslationManager::getInstance()->translations->createOrUpdateTranslation(
    $fixtureSource,
    'site.disposable-fixture',
);
if ($translation === null) {
    throw new RuntimeException('Unable to seed the disposable translation baseline.');
}
$translationIds = (new craft\db\Query())
    ->select(['id'])
    ->from('{{%translationmanager_translations}}')
    ->where(['source' => $fixtureSource])
    ->column();
if (count($translationIds) !== 3) {
    throw new RuntimeException('Disposable translation baseline must contain one row per fixture language.');
}

fwrite(STDOUT, json_encode([
    'primarySiteId' => $primary->id,
    'primarySiteLanguage' => $primary->language,
    'languages' => $languages,
    'createdSites' => $created,
    'translationIds' => array_map('intval', $translationIds),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
