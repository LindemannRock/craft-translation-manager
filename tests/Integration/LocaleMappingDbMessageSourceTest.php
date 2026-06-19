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
use craft\helpers\Db;
use lindemannrock\translationmanager\i18n\HybridLocaleMappingMessageSource;
use lindemannrock\translationmanager\i18n\LocaleMappingDbMessageSource;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.29.0
 */
final class LocaleMappingDbMessageSourceTest extends TestCase
{
    private string $managedPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->managedPath = sys_get_temp_dir() . '/translation-manager-db-source-' . bin2hex(random_bytes(4));
        mkdir($this->managedPath . '/de', 0775, true);

        file_put_contents(
            $this->managedPath . '/de/example.php',
            "<?php\nreturn [\n    'PHP only' => 'PHP only DE',\n    'Shared' => 'PHP shared DE',\n];\n",
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->managedPath);
        parent::tearDown();
    }

    public function testDatabaseMessagesLoadByTranslationKey(): void
    {
        $this->requireAtLeastOneSite();
        $this->createTranslatedRow('example', 'DB only', 'DB only DE', 'de');

        $source = new LocaleMappingDbMessageSource();
        $source->sourceLanguage = 'en';
        $source->forceTranslation = true;

        self::assertSame('DB only DE', $source->translate('example', 'DB only', 'de'));
    }

    public function testLocaleMappingAppliesToDatabaseMessages(): void
    {
        $this->requireAtLeastOneSite();
        $this->createTranslatedRow('example', 'Mapped DB', 'Mapped DB DE', 'de');

        $source = new LocaleMappingDbMessageSource();
        $source->sourceLanguage = 'en';
        $source->forceTranslation = true;
        $source->localeMapping = [
            'de-CH' => 'de',
        ];

        self::assertSame('Mapped DB DE', $source->translate('example', 'Mapped DB', 'de-CH'));
    }

    public function testHybridMessagesOverlayDatabaseOnPhpFallback(): void
    {
        $this->requireAtLeastOneSite();
        $this->createTranslatedRow('example', 'Shared', 'DB shared DE', 'de');

        $source = new HybridLocaleMappingMessageSource();
        $source->sourceLanguage = 'en';
        $source->basePath = $this->managedPath;
        $source->forceTranslation = true;
        $source->fileMap = [
            'example' => 'example.php',
        ];

        self::assertSame('PHP only DE', $source->translate('example', 'PHP only', 'de'));
        self::assertSame('DB shared DE', $source->translate('example', 'Shared', 'de'));
    }

    private function createTranslatedRow(string $category, string $key, string $translation, string $language): void
    {
        $site = Craft::$app->getSites()->getAllSites()[0];
        $now = Db::prepareDateForDb(new \DateTimeImmutable());

        $record = new TranslationRecord();
        $record->source = self::MARKER . $key;
        $record->sourceHash = md5($record->source);
        $record->context = 'site.php-db-message-source-test';
        $record->category = $category;
        $record->translationKey = $key;
        $record->translation = $translation;
        $record->siteId = (int)$site->id;
        $record->language = $language;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        $record->dateCreated = $now;
        $record->dateUpdated = $now;

        self::assertTrue($record->save(false));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $target = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($target)) {
                $this->removeDirectory($target);
                continue;
            }

            unlink($target);
        }

        rmdir($path);
    }
}
