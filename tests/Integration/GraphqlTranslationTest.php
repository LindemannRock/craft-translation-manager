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
use craft\helpers\StringHelper;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\translationmanager\gql\queries\TranslationQuery;
use lindemannrock\translationmanager\gql\resolvers\TranslationResolver;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * Covers Translation Manager's read-only GraphQL query contract.
 *
 * @since 5.26.0
 */
final class GraphqlTranslationTest extends TestCase
{
    public function testQueryDefinitionsExposeTranslateAndListQueriesWithoutTokenCheck(): void
    {
        $queries = TranslationQuery::getQueries(false);

        self::assertArrayHasKey('translationManagerTranslate', $queries);
        self::assertArrayHasKey('translationManagerTranslations', $queries);
        self::assertArrayHasKey('key', $queries['translationManagerTranslate']['args']);
        self::assertArrayHasKey('category', $queries['translationManagerTranslate']['args']);
        self::assertArrayHasKey('language', $queries['translationManagerTranslate']['args']);
        self::assertArrayHasKey('status', $queries['translationManagerTranslations']['args']);
        self::assertArrayHasKey('origin', $queries['translationManagerTranslations']['args']);
        self::assertArrayHasKey('search', $queries['translationManagerTranslations']['args']);
        self::assertArrayHasKey('limit', $queries['translationManagerTranslations']['args']);
    }

    public function testQueryDefinitionsAreSchemaPermissionGated(): void
    {
        self::assertSame([], TranslationQuery::getQueries());
    }

    public function testTranslateLooksUpSingleRowWithoutCreatingMissingRows(): void
    {
        $source = $this->markerSource('lookup');
        $record = $this->seedTranslation([
            'source' => $source,
            'translationKey' => 'Download our app',
            'translation' => 'نزّل تطبيقنا',
            'language' => 'ar',
            'category' => 'messages',
            'status' => 'translated',
        ]);
        $beforeCount = $this->countRows(TranslationRecord::tableName(), []);

        $result = TranslationResolver::resolve(
            null,
            [
                'key' => 'Download our app',
                'category' => 'messages',
                'language' => 'ar',
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($result);
        self::assertSame($record->id, $result['id']);
        self::assertSame('Download our app', $result['key']);
        self::assertSame($source, $result['source']);
        self::assertSame('نزّل تطبيقنا', $result['translation']);
        self::assertSame('translated', $result['status']);
        self::assertSame('manual', $result['origin']);
        self::assertSame($beforeCount, $this->countRows(TranslationRecord::tableName(), []));

        $missing = TranslationResolver::resolve(
            null,
            [
                'key' => 'Missing key',
                'category' => 'messages',
                'language' => 'ar',
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertNull($missing);
        self::assertSame($beforeCount, $this->countRows(TranslationRecord::tableName(), []));
    }

    public function testTranslationsListAppliesFiltersAndLimit(): void
    {
        $translated = $this->seedTranslation([
            'source' => $this->markerSource('translated'),
            'translationKey' => 'Translated key',
            'translation' => 'مترجم',
            'language' => 'ar',
            'category' => 'messages',
            'status' => 'translated',
            'translationOrigin' => 'manual',
        ]);
        $this->seedTranslation([
            'source' => $this->markerSource('pending'),
            'translationKey' => 'Pending key',
            'translation' => null,
            'language' => 'ar',
            'category' => 'messages',
            'status' => 'pending',
            'translationOrigin' => 'system',
        ]);
        $this->seedTranslation([
            'source' => $this->markerSource('other_language'),
            'translationKey' => 'Other language key',
            'translation' => 'Übersetzt',
            'language' => 'de',
            'category' => 'messages',
            'status' => 'translated',
            'translationOrigin' => 'manual',
        ]);

        $results = TranslationResolver::resolveAll(
            null,
            [
                'language' => 'ar',
                'category' => 'messages',
                'status' => 'translated',
                'origin' => 'manual',
                'limit' => 10,
            ],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertIsArray($results);
        $ids = array_column($results, 'id');
        self::assertContains($translated->id, $ids);
        self::assertNotContains(null, $ids);
        foreach ($results as $row) {
            self::assertSame('ar', $row['language']);
            self::assertSame('messages', $row['category']);
            self::assertSame('translated', $row['status']);
            self::assertSame('manual', $row['origin']);
        }
    }

    public function testTranslationsListCapsLimitAtFiveHundred(): void
    {
        $query = TranslationQuery::getQueries(false)['translationManagerTranslations'];

        self::assertSame('Lists translation rows. This query is read-only.', $query['description']);

        $results = TranslationResolver::resolveAll(
            null,
            ['limit' => 1000, 'search' => self::MARKER],
            null,
            $this->createMock(ResolveInfo::class),
        );

        self::assertLessThanOrEqual(500, count($results));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedTranslation(array $overrides = []): TranslationRecord
    {
        $source = (string)($overrides['source'] ?? $this->markerSource('row'));
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->context = (string)($overrides['context'] ?? 'site.messages');
        $record->category = (string)($overrides['category'] ?? 'messages');
        $record->siteId = (int)($overrides['siteId'] ?? Craft::$app->getSites()->getPrimarySite()->id);
        $record->language = (string)($overrides['language'] ?? 'ar');
        $record->translationKey = (string)($overrides['translationKey'] ?? $source);
        $record->translation = $overrides['translation'] ?? null;
        $record->status = (string)($overrides['status'] ?? 'pending');
        $record->translationOrigin = (string)($overrides['translationOrigin'] ?? 'manual');
        $record->usageCount = (int)($overrides['usageCount'] ?? 1);
        $record->dateCreated = Db::prepareDateForDb(new \DateTime());
        $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
        $record->uid = StringHelper::UUID();

        self::assertTrue($record->save(), 'Seeded translation must save: ' . json_encode($record->getErrors()));

        return $record;
    }

    private function markerSource(string $suffix): string
    {
        return self::MARKER . str_replace('_', '', $suffix) . '_' . StringHelper::randomString(8);
    }
}
