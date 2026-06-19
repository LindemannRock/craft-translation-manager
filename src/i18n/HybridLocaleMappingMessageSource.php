<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Message source that overlays database translations on PHP/native fallbacks.
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\i18n;

/**
 * HybridLocaleMappingMessageSource
 *
 * Loads generated/native PHP translations first, then overlays Translation
 * Manager database rows. This keeps existing PHP translations available while
 * making database rows the runtime source of truth when present.
 *
 * @since 5.29.0
 */
class HybridLocaleMappingMessageSource extends LocaleMappingDbMessageSource
{
    /**
     * @var string|null Base path for plugin-owned translation files.
     */
    public ?string $fallbackBasePath = null;

    /**
     * @inheritdoc
     *
     * @return array<string,string>
     */
    protected function loadMessages($category, $language): array
    {
        $phpMessages = $this->loadPhpMessages((string)$category, (string)$language);
        $dbMessages = parent::loadMessages($category, $language);

        return array_merge($phpMessages, $dbMessages);
    }

    /**
     * @return array<string,string>
     */
    private function loadPhpMessages(string $category, string $language): array
    {
        if ($this->fallbackBasePath === null) {
            $source = new class() extends LocaleMappingPhpMessageSource {
                /**
                 * @return array<string,string>
                 */
                public function exportMessages(string $category, string $language): array
                {
                    return $this->loadMessages($category, $language);
                }
            };
        } else {
            $source = new class() extends MergedLocaleMappingPhpMessageSource {
                /**
                 * @return array<string,string>
                 */
                public function exportMessages(string $category, string $language): array
                {
                    return $this->loadMessages($category, $language);
                }
            };
            $source->fallbackBasePath = $this->fallbackBasePath;
        }

        $source->sourceLanguage = $this->sourceLanguage;
        $source->basePath = (string)$this->basePath;
        $source->forceTranslation = $this->forceTranslation;
        $source->fileMap = $this->fileMap;
        $source->localeMapping = $this->localeMapping;

        return $source->exportMessages($category, $language);
    }
}
