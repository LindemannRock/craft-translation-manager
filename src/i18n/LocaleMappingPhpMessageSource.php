<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Custom PHP message source with locale mapping support
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\i18n;

use yii\i18n\PhpMessageSource;

/**
 * LocaleMappingPhpMessageSource
 *
 * Extends Yii's PhpMessageSource to support locale mapping.
 * Maps regional locale variants to base locales (e.g., en-GB -> en, fr-CA -> fr)
 * to reduce translation duplication.
 *
 * @since 1.0.0
 */
class LocaleMappingPhpMessageSource extends PhpMessageSource
{
    /**
     * @var array<string, string> Mapping of source locales to destination locales
     *                            e.g., ['en-US' => 'en', 'fr-CA' => 'fr']
     */
    public array $localeMapping = [];

    /**
     * @inheritdoc
     *
     * Applies locale mapping before loading messages.
     * If the language matches a source in the mapping, it will load
     * messages from the mapped destination locale instead.
     */
    protected function loadMessages($category, $language)
    {
        // Apply locale mapping if configured
        if (isset($this->localeMapping[$language])) {
            $language = $this->localeMapping[$language];
        }

        return parent::loadMessages($category, $language);
    }

    /**
     * Maps a language code using the configured locale mapping.
     *
     * @param string $language The original language code
     * @return string The mapped language code (or original if no mapping exists)
     */
    public function mapLanguage(string $language): string
    {
        return $this->localeMapping[$language] ?? $language;
    }
}
