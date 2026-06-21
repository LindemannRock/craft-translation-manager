<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\gql\resolvers;

use craft\db\Query;
use craft\gql\base\Resolver;
use GraphQL\Type\Definition\ResolveInfo;
use lindemannrock\translationmanager\helpers\FeatureGate;
use lindemannrock\translationmanager\records\TranslationRecord;

/**
 * GraphQL resolver for Translation Manager translations.
 *
 * @since 5.26.0
 */
class TranslationResolver extends Resolver
{
    /**
     * Resolve one translation row by key/category/language.
     *
     * @inheritdoc
     */
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): ?array
    {
        $key = trim((string)($arguments['key'] ?? ''));
        $category = trim((string)($arguments['category'] ?? 'messages'));
        $language = trim((string)($arguments['language'] ?? ''));

        if ($key === '' || $category === '' || $language === '') {
            return null;
        }

        $row = self::baseQuery()
            ->where([
                'translationKey' => $key,
                'category' => $category,
                'language' => $language,
            ])
            ->one();

        return is_array($row) ? self::normalizeRow($row) : null;
    }

    /**
     * List translation rows with optional filters.
     *
     * @inheritdoc
     */
    public static function resolveAll(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): array
    {
        $query = self::baseQuery();

        foreach (['language', 'category', 'status', 'context'] as $filter) {
            if (!empty($arguments[$filter])) {
                $query->andWhere([$filter => trim((string)$arguments[$filter])]);
            }
        }

        if (!empty($arguments['origin'])) {
            $origin = trim((string)$arguments['origin']);
            if ($origin === 'ai' && !FeatureGate::aiTranslationsEnabled()) {
                return [];
            }

            $query->andWhere(['translationOrigin' => $origin]);
        }

        if (!empty($arguments['search'])) {
            $searchTerm = trim((string)$arguments['search']);
            if ($searchTerm !== '') {
                $searchPattern = '%' . strtr($searchTerm, ['%' => '\%', '_' => '\_', '\\' => '\\\\']) . '%';
                $query->andWhere([
                    'or',
                    ['like', 'translationKey', $searchPattern, false],
                    ['like', 'source', $searchPattern, false],
                    ['like', 'translation', $searchPattern, false],
                    ['like', 'context', $searchPattern, false],
                ]);
            }
        }

        $limit = $arguments['limit'] ?? null;
        if (is_numeric($limit) && (int)$limit > 0) {
            $query->limit(min((int)$limit, 500));
        } else {
            $query->limit(100);
        }

        return array_map(
            static fn(array $row): array => self::normalizeRow($row),
            $query
                ->orderBy(['category' => SORT_ASC, 'translationKey' => SORT_ASC, 'language' => SORT_ASC])
                ->all(),
        );
    }

    private static function baseQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'key' => 'translationKey',
                'source',
                'translation',
                'category',
                'language',
                'context',
                'status',
                'origin' => 'translationOrigin',
                'usageCount',
                'lastUsed',
            ])
            ->from(TranslationRecord::tableName());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row): array
    {
        $row['id'] = isset($row['id']) ? (int)$row['id'] : null;
        $row['usageCount'] = isset($row['usageCount']) ? (int)$row['usageCount'] : null;
        if (($row['origin'] ?? null) === 'ai' && !FeatureGate::aiTranslationsEnabled()) {
            $row['origin'] = 'system';
        }

        return $row;
    }
}
