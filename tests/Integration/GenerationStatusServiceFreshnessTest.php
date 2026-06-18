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
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * @since 5.28.0
 */
#[CoversClass(\lindemannrock\translationmanager\services\GenerationStatusService::class)]
final class GenerationStatusServiceFreshnessTest extends TestCase
{
    private ?string $originalTranslationsAlias = null;

    private ?string $tempTranslationsPath = null;

    private string $originalGenerationPath;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = TranslationManager::getInstance()->getSettings();
        $this->originalGenerationPath = $settings->generationPath;
        $this->originalTranslationsAlias = Craft::getAlias('@translations', false) ?: null;

        $this->tempTranslationsPath = Craft::$app->getPath()->getTempPath()
            . '/translation-manager-generation-status-test-' . bin2hex(random_bytes(6));
        FileHelper::createDirectory($this->tempTranslationsPath);

        Craft::setAlias('@translations', $this->tempTranslationsPath);
        $settings->generationPath = '@translations';

        $this->deleteGenerationQueueRows();
    }

    protected function tearDown(): void
    {
        $this->deleteGenerationQueueRows();

        TranslationManager::getInstance()->getSettings()->generationPath = $this->originalGenerationPath;

        if ($this->originalTranslationsAlias !== null) {
            Craft::setAlias('@translations', $this->originalTranslationsAlias);
        }

        if ($this->tempTranslationsPath !== null && is_dir($this->tempTranslationsPath)) {
            FileHelper::removeDirectory($this->tempTranslationsPath);
        }

        parent::tearDown();
    }

    public function testFreshnessCheckQueuesOneGenerationJobForMissingGeneratedFiles(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $site = Craft::$app->getSites()->getAllSites()[0];
        $source = self::MARKER . 'freshness_' . bin2hex(random_bytes(4));
        $category = TranslationManager::getInstance()->getSettings()->getPrimaryCategory();
        $now = Db::prepareDateForDb(new \DateTimeImmutable());

        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->context = 'site.php-generation-status-test';
        $record->category = $category;
        $record->translationKey = $source;
        $record->translation = 'Translated freshness value ' . bin2hex(random_bytes(4));
        $record->siteId = (int)$site->id;
        $record->language = (string)$site->language;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        $record->dateCreated = $now;
        $record->dateUpdated = $now;

        self::assertTrue($record->save(false));

        TranslationManager::getInstance()->generationStatus->maybeQueueFreshnessGeneration();
        TranslationManager::getInstance()->generationStatus->maybeQueueFreshnessGeneration();

        self::assertSame(1, $this->countGenerationQueueRows());
    }

    private function countGenerationQueueRows(): int
    {
        return (int)$this->generationQueueQuery()->count();
    }

    private function deleteGenerationQueueRows(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%queue}}', [
                'and',
                ['like', 'job', 'translationmanager'],
                ['like', 'job', 'GenerateTranslationsJob'],
                ['like', 'job', 'generation-freshness'],
            ])
            ->execute();
    }

    private function generationQueueQuery(): Query
    {
        return (new Query())
            ->from('{{%queue}}')
            ->where(['like', 'job', 'translationmanager'])
            ->andWhere(['like', 'job', 'GenerateTranslationsJob'])
            ->andWhere(['like', 'job', 'generation-freshness']);
    }
}
