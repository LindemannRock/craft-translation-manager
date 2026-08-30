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
use lindemannrock\translationmanager\listeners\MissingTranslationListener;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use yii\i18n\MissingTranslationEvent;

/**
 * Pins the trim-based translation-value rule across persistence and capture.
 *
 * @since 5.35.0
 */
final class TranslationValuePresenceTest extends TestCase
{
    #[DataProvider('translationValueProvider')]
    public function testServiceSaveDerivesStatusFromExplicitValuePresence(
        ?string $value,
        bool $expectedPresent,
    ): void {
        $record = $this->createRecord(
            self::MARKER . 'service_value_' . bin2hex(random_bytes(4)),
            $value,
            'pending',
        );

        self::assertTrue($this->translations->saveTranslation($record));
        self::assertSame($expectedPresent ? 'translated' : 'pending', $record->status);
    }

    #[DataProvider('translationValueProvider')]
    public function testRuntimeCaptureAcceptsZeroAndRejectsAbsentMessages(
        ?string $value,
        bool $expectedPresent,
    ): void {
        $settings = $this->settings();
        $category = 'tm-test-missing-' . bin2hex(random_bytes(4));
        $language = Craft::$app->getSites()->getPrimarySite()->language;
        $settings->captureMissingTranslations = true;
        $settings->captureMissingOnlyDevMode = false;
        $settings->translationCategories = [['key' => $category, 'enabled' => true]];
        MissingTranslationListener::resetCaches();

        try {
            MissingTranslationListener::handle(new MissingTranslationEvent([
                'category' => $category,
                'message' => $value,
                'language' => $language,
            ]));

            $record = TranslationRecord::findOne([
                'sourceHash' => md5((string)$value),
                'category' => $category,
                'language' => $settings->mapLanguage($language),
            ]);
            self::assertSame($expectedPresent, $record !== null);
            if ($record !== null) {
                self::assertSame((string)$value, $record->source);
                self::assertSame('pending', $record->status);
            }
        } finally {
            MissingTranslationListener::resetCaches();
            TranslationRecord::deleteAll(['category' => $category]);
        }
    }

    #[DataProvider('translationValueProvider')]
    public function testRecaptureReactivatesUnusedRowsFromExplicitValuePresence(
        ?string $value,
        bool $expectedPresent,
    ): void {
        $source = self::MARKER . 'recapture_value_' . bin2hex(random_bytes(4));
        $category = $this->settings()->getPrimaryCategory();
        self::assertNotNull($this->translations->createOrUpdateTranslation($source, 'site.recapture', $category));

        /** @var TranslationRecord[] $records */
        $records = TranslationRecord::find()
            ->where(['sourceHash' => md5($source), 'category' => $category])
            ->all();
        self::assertNotEmpty($records);
        foreach ($records as $record) {
            $record->translation = $value;
            $record->status = 'unused';
            self::assertTrue($record->save(false));
        }

        self::assertNotNull($this->translations->createOrUpdateTranslation($source, 'site.recapture', $category));

        foreach (TranslationRecord::findAll(['sourceHash' => md5($source), 'category' => $category]) as $record) {
            self::assertSame($expectedPresent ? 'translated' : 'pending', $record->status);
        }
    }

    /**
     * @return array<string,array{0:?string,1:bool}>
     */
    public static function translationValueProvider(): array
    {
        return [
            'zero' => ['0', true],
            'normal text' => ['Translated text', true],
            'empty string' => ['', false],
            'whitespace' => [" \t\n", false],
            'null' => [null, false],
        ];
    }

    private function createRecord(string $source, ?string $translation, string $status): TranslationRecord
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->context = 'site.value-presence';
        $record->category = $this->settings()->getPrimaryCategory();
        $record->translationKey = $source;
        $record->translation = $translation;
        $record->siteId = (int)$site->id;
        $record->language = $this->settings()->mapLanguage($site->language);
        $record->status = $status;
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        self::assertTrue($record->save(), json_encode($record->getErrors()));

        return $record;
    }
}
