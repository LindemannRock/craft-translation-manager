<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * PHP message source that overlays generated translations on plugin translations.
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\i18n;

use craft\i18n\PhpMessageSource;

/**
 * MergedLocaleMappingPhpMessageSource
 *
 * Loads a plugin's native translation file first, then overlays Translation
 * Manager's generated file. This preserves plugin-owned static translations
 * for categories such as `formie` or `freeform` while still allowing managed
 * site content to override matching source strings.
 *
 * @since 5.26.0
 */
class MergedLocaleMappingPhpMessageSource extends LocaleMappingPhpMessageSource
{
    /**
     * @var string|null Base path for the plugin-owned translation files.
     */
    public ?string $fallbackBasePath = null;

    /**
     * @inheritdoc
     */
    protected function loadMessages($category, $language)
    {
        $managedMessages = parent::loadMessages($category, $language);
        $fallbackMessages = $this->loadFallbackMessagesFromPlugin($category, $this->mapLanguage((string)$language));

        return array_merge($fallbackMessages, $managedMessages);
    }

    /**
     * Load native plugin messages without site overrides.
     *
     * Translation Manager's generated files are the intended override layer,
     * so the fallback source deliberately avoids Craft's @translations
     * override loading.
     *
     * @return array<string, string>
     */
    private function loadFallbackMessagesFromPlugin(string $category, string $language): array
    {
        if ($this->fallbackBasePath === null || !is_dir($this->fallbackBasePath)) {
            return [];
        }

        $source = new class() extends PhpMessageSource {
            /**
             * @return array<string, string>
             */
            public function exportMessages(string $category, string $language): array
            {
                return $this->loadMessages($category, $language);
            }
        };
        $source->sourceLanguage = $this->sourceLanguage;
        $source->basePath = $this->fallbackBasePath;
        $source->forceTranslation = $this->forceTranslation;
        $source->allowOverrides = false;
        $source->fileMap = [
            $category => $category . '.php',
        ];

        return $source->exportMessages($category, $language);
    }
}
