<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Translation Manager fallback service for Freeform translations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\freeform;

use Craft;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Library\Translations\TranslationTable;
use Solspace\Freeform\Services\Form\TranslationsService;

/**
 * Translation Manager fallback service for Freeform translations.
 *
 * Freeform's native per-site translation records remain authoritative. When
 * they do not contain a requested value, this service falls back to generated
 * Translation Manager `freeform.php` messages.
 *
 * @since 5.26.0
 */
class TmTranslationsService extends TranslationsService
{
    /**
     * @inheritdoc
     */
    public function getTranslation(
        Form $form,
        string $type,
        string $namespace,
        string $handle,
        mixed $defaultValue,
    ): mixed {
        $nativeValue = parent::getTranslationTable($form, $type, $namespace)->get($handle, self::missingMarker());
        if ($nativeValue !== self::missingMarker()) {
            return $nativeValue;
        }

        if (!is_scalar($defaultValue) || (string)$defaultValue === '') {
            return $defaultValue;
        }

        return Craft::t('freeform', (string)$defaultValue);
    }

    /**
     * @inheritdoc
     */
    public function getTranslationTable(Form $form, string $type, string $namespace): TranslationTable
    {
        return new TmTranslationTable(parent::getTranslationTable($form, $type, $namespace), $form, $type, $namespace);
    }

    private static function missingMarker(): string
    {
        return '__translation_manager_missing__';
    }
}
