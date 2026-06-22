<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Translation source model
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\models;

/**
 * Describes one source of managed translations.
 *
 * @since 5.30.0
 */
class TranslationSource
{
    public const TYPE_CATEGORY = 'category';
    public const TYPE_PROVIDER = 'provider';

    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $type,
        public readonly string $category,
        public readonly ?string $providerName = null,
        public readonly ?string $contextPrefix = null,
        public readonly ?string $pluginHandle = null,
    ) {
    }

    public function isProvider(): bool
    {
        return $this->type === self::TYPE_PROVIDER;
    }
}
