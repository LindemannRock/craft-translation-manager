<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Database-backed message source with locale mapping support.
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\i18n;

use craft\db\Query;
use lindemannrock\translationmanager\records\TranslationRecord;
use yii\i18n\MessageSource;

/**
 * LocaleMappingDbMessageSource
 *
 * Reads translated strings directly from Translation Manager's database rows.
 *
 * @since 5.29.0
 */
class LocaleMappingDbMessageSource extends MessageSource
{
    /**
     * @var array<string, string> Mapping of source locales to destination locales.
     */
    public array $localeMapping = [];

    /**
     * @var string|null Accepted for compatibility with PHP message-source config.
     */
    public ?string $basePath = null;

    /**
     * @var array<string,string> Accepted for compatibility with PHP message-source config.
     */
    public array $fileMap = [];

    /**
     * @var string|null Accepted for compatibility with provider category config.
     */
    public ?string $fallbackBasePath = null;

    /**
     * @inheritdoc
     *
     * @return array<string,string>
     */
    protected function loadMessages($category, $language): array
    {
        $language = $this->mapLanguage((string)$language);

        /** @var list<array{translationKey:string,translation:string}> $rows */
        $rows = (new Query())
            ->select(['translationKey', 'translation'])
            ->from(TranslationRecord::tableName())
            ->where([
                'category' => $category,
                'language' => $language,
                'status' => 'translated',
            ])
            ->andWhere(['not', ['translation' => null]])
            ->andWhere(['<>', 'translation', ''])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $messages = [];
        foreach ($rows as $row) {
            $messages[(string)$row['translationKey']] = (string)$row['translation'];
        }

        return $messages;
    }

    /**
     * @inheritdoc
     */
    protected function translateMessage($category, $message, $language)
    {
        $translation = parent::translateMessage($category, $message, $language);
        if ($translation !== false) {
            return $translation;
        }

        $trimmedMessage = trim($message);
        if ($trimmedMessage === $message || $trimmedMessage === '') {
            return false;
        }

        return parent::translateMessage($category, $trimmedMessage, $language);
    }

    public function mapLanguage(string $language): string
    {
        return $this->localeMapping[$language] ?? $language;
    }
}
