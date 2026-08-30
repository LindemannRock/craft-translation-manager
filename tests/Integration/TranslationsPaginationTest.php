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
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\TranslationsService;
use lindemannrock\translationmanager\tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionMethod;
use yii\db\Query;

/**
 * Pins bounded CP pagination to the unbounded catalogue's filter/sort contract.
 *
 * @since 5.35.0
 */
#[CoversClass(TranslationsService::class)]
final class TranslationsPaginationTest extends TestCase
{
    public function testCountAndPageBoundariesMatchTheCompleteFilteredCatalogue(): void
    {
        $fixture = $this->seedCatalogue(23);
        $criteria = $this->criteria($fixture['token']);
        $complete = $this->translations->getTranslations($criteria);

        self::assertCount(23, $complete);
        $this->assertPageMatches($criteria, $complete, 10, 0);
        $this->assertPageMatches($criteria, $complete, 10, 10);
        $this->assertPageMatches($criteria, $complete, 10, 20);
        $this->assertPageMatches($criteria, $complete, 10, 30);
    }

    public function testFiltersSearchAndSortKeepTheUnboundedLogicalResult(): void
    {
        $fixture = $this->seedCatalogue(24);
        $base = $this->criteria($fixture['token']);
        $scenarios = [
            ['status' => 'draft', 'sort' => 'translationKey', 'dir' => 'desc'],
            ['origin' => 'import', 'sort' => 'status', 'dir' => 'asc'],
            ['category' => $fixture['secondaryCategory'], 'sort' => 'category', 'dir' => 'desc'],
            ['type' => 'site', 'sort' => 'type', 'dir' => 'asc'],
            ['search' => $fixture['token'] . '_translation', 'sort' => 'translation', 'dir' => 'desc'],
        ];

        foreach ($scenarios as $scenario) {
            $criteria = array_replace($base, $scenario);
            $complete = $this->translations->getTranslations($criteria);
            self::assertNotEmpty($complete, json_encode($scenario, JSON_THROW_ON_ERROR));
            $this->assertPageMatches($criteria, $complete, 5, 3);
        }
    }

    public function testDatabaseLimitAndOffsetAreAppliedBeforePageRowsAreReturned(): void
    {
        $fixture = $this->seedCatalogue(17);
        $criteria = $this->criteria($fixture['token']);
        $page = $this->translations->getTranslationsPage($criteria, 5, 5);
        $method = new ReflectionMethod(TranslationsService::class, 'buildTranslationsQuery');
        $method->setAccessible(true);
        $query = $method->invoke($this->translations, $criteria);
        self::assertInstanceOf(Query::class, $query);
        $sql = $query->limit(5)->offset(5)->createCommand()->getRawSql();

        self::assertSame(17, $page['totalCount']);
        self::assertCount(5, $page['translations']);
        self::assertMatchesRegularExpression('/LIMIT\s+5\s+OFFSET\s+5/i', $sql);
    }

    public function testUnboundedConsumersStillReceiveEveryMatchingRow(): void
    {
        $fixture = $this->seedCatalogue(12);
        $criteria = $this->criteria($fixture['token']);

        $complete = $this->translations->getTranslations($criteria);
        $bounded = $this->translations->getTranslationsPage($criteria, 4, 0);

        self::assertCount(12, $complete);
        self::assertSame(12, $bounded['totalCount']);
        self::assertCount(4, $bounded['translations']);
        self::assertSame(
            array_slice(array_column($complete, 'id'), 0, 4),
            array_column($bounded['translations'], 'id'),
        );
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<int, array<string, mixed>> $complete
     */
    private function assertPageMatches(array $criteria, array $complete, int $limit, int $offset): void
    {
        $page = $this->translations->getTranslationsPage($criteria, $limit, $offset);

        self::assertSame(count($complete), $page['totalCount']);
        self::assertSame(
            array_column(array_slice($complete, $offset, $limit), 'id'),
            array_column($page['translations'], 'id'),
        );
        self::assertLessThanOrEqual($limit, count($page['translations']));
    }

    /** @return array<string, mixed> */
    private function criteria(string $search): array
    {
        return [
            'language' => $this->settings()->mapLanguage(Craft::$app->getSites()->getPrimarySite()->language),
            'status' => 'all',
            'search' => $search,
            'sort' => 'translationKey',
            'dir' => 'asc',
            'type' => 'all',
            'origin' => 'all',
            'category' => 'all',
        ];
    }

    /** @return array{token: string, secondaryCategory: string} */
    private function seedCatalogue(int $count): array
    {
        $token = self::MARKER . 'pagination_' . bin2hex(random_bytes(4));
        $primaryCategory = $this->settings()->getPrimaryCategory();
        $secondaryCategory = 'tm-test-pagination';
        $site = Craft::$app->getSites()->getPrimarySite();
        $language = $this->settings()->mapLanguage($site->language);

        for ($index = 0; $index < $count; $index++) {
            $source = sprintf('%s_source_%03d', $token, $index);
            $record = new TranslationRecord();
            $record->source = $source;
            $record->sourceHash = md5($source);
            $record->context = 'site.pagination';
            $record->category = $index % 2 === 0 ? $primaryCategory : $secondaryCategory;
            $record->translationKey = sprintf('%s_key_%03d', $token, $index);
            $record->translation = sprintf('%s_translation_%03d', $token, $count - $index);
            $record->siteId = (int)$site->id;
            $record->language = $language;
            $record->status = match ($index % 3) {
                0 => 'translated',
                1 => 'draft',
                default => 'pending',
            };
            $record->translationOrigin = $index % 2 === 0 ? 'manual' : 'import';
            $record->usageCount = 1;
            $record->lastUsed = Db::prepareDateForDb(new \DateTime());
            $record->dateCreated = Db::prepareDateForDb(new \DateTime());
            $record->dateUpdated = Db::prepareDateForDb(new \DateTime());
            $record->uid = StringHelper::UUID();
            self::assertTrue($record->save(), json_encode($record->getErrors(), JSON_THROW_ON_ERROR));
        }

        return ['token' => $token, 'secondaryCategory' => $secondaryCategory];
    }
}
