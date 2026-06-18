<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\gql\queries;

use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use lindemannrock\base\helpers\GqlHelper;
use lindemannrock\translationmanager\gql\resolvers\TranslationResolver;
use lindemannrock\translationmanager\gql\types\TranslationType;

/**
 * GraphQL queries for Translation Manager.
 *
 * @since 5.26.0
 */
class TranslationQuery extends Query
{
    /**
     * @inheritdoc
     */
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !GqlHelper::canQuery('translationManager.all')) {
            return [];
        }

        return [
            'translationManagerTranslate' => [
                'type' => TranslationType::getType(),
                'args' => [
                    'key' => [
                        'name' => 'key',
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'The translation key to look up.',
                    ],
                    'category' => [
                        'name' => 'category',
                        'type' => Type::string(),
                        'description' => 'The Craft translation category. Defaults to messages.',
                    ],
                    'language' => [
                        'name' => 'language',
                        'type' => Type::nonNull(Type::string()),
                        'description' => 'The language code to look up.',
                    ],
                ],
                'resolve' => TranslationResolver::class . '::resolve',
                'description' => 'Looks up a single translation row without creating or updating records.',
            ],
            'translationManagerTranslations' => [
                'type' => Type::listOf(TranslationType::getType()),
                'args' => [
                    'language' => [
                        'name' => 'language',
                        'type' => Type::string(),
                        'description' => 'Filter by language code.',
                    ],
                    'category' => [
                        'name' => 'category',
                        'type' => Type::string(),
                        'description' => 'Filter by Craft translation category.',
                    ],
                    'status' => [
                        'name' => 'status',
                        'type' => Type::string(),
                        'description' => 'Filter by translation status.',
                    ],
                    'origin' => [
                        'name' => 'origin',
                        'type' => Type::string(),
                        'description' => 'Filter by translation origin.',
                    ],
                    'context' => [
                        'name' => 'context',
                        'type' => Type::string(),
                        'description' => 'Filter by translation context.',
                    ],
                    'search' => [
                        'name' => 'search',
                        'type' => Type::string(),
                        'description' => 'Search keys, source text, translations, and contexts.',
                    ],
                    'limit' => [
                        'name' => 'limit',
                        'type' => Type::int(),
                        'description' => 'The maximum number of rows to return, capped at 500. Defaults to 100.',
                    ],
                ],
                'resolve' => TranslationResolver::class . '::resolveAll',
                'description' => 'Lists translation rows. This query is read-only.',
            ],
        ];
    }
}
