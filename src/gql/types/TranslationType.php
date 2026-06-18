<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\gql\types;

use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use lindemannrock\base\helpers\GqlHelper;

/**
 * GraphQL object type for Translation Manager translation rows.
 *
 * @since 5.26.0
 */
class TranslationType extends ObjectType
{
    public static function getType(): Type
    {
        $typeName = self::getName();
        if ($type = GqlEntityRegistry::getEntity($typeName)) {
            return $type;
        }

        return GqlEntityRegistry::createEntity($typeName, new self([
            'name' => $typeName,
            'fields' => self::class . '::getFieldDefinitions',
            'description' => 'A Translation Manager translation row.',
        ]));
    }

    public static function getName(): string
    {
        return 'TranslationManagerTranslation';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getFieldDefinitions(): array
    {
        return [
            'id' => [
                'name' => 'id',
                'type' => Type::int(),
                'description' => 'The translation row ID.',
            ],
            'key' => [
                'name' => 'key',
                'type' => Type::string(),
                'description' => 'The translation key.',
            ],
            'source' => [
                'name' => 'source',
                'type' => Type::string(),
                'description' => 'The source text.',
            ],
            'translation' => [
                'name' => 'translation',
                'type' => Type::string(),
                'description' => 'The translated text.',
            ],
            'category' => [
                'name' => 'category',
                'type' => Type::string(),
                'description' => 'The Craft translation category.',
            ],
            'language' => [
                'name' => 'language',
                'type' => Type::string(),
                'description' => 'The language code.',
            ],
            'context' => [
                'name' => 'context',
                'type' => Type::string(),
                'description' => 'The translation context.',
            ],
            'status' => [
                'name' => 'status',
                'type' => Type::string(),
                'description' => 'The translation status.',
            ],
            'origin' => [
                'name' => 'origin',
                'type' => Type::string(),
                'description' => 'The translation origin.',
            ],
            'usageCount' => [
                'name' => 'usageCount',
                'type' => Type::int(),
                'description' => 'The usage count.',
            ],
            'lastUsed' => [
                'name' => 'lastUsed',
                'type' => Type::string(),
                'description' => 'The last-used datetime.',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): mixed
    {
        if (!is_array($source)) {
            return parent::resolve($source, $arguments, $context, $resolveInfo);
        }

        return GqlHelper::nullIfEmptyString($source[$resolveInfo->fieldName] ?? null);
    }
}
