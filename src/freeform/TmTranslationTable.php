<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Translation Manager fallback table for Freeform translations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\freeform;

use Craft;
use Solspace\Freeform\Fields\Properties\Options\OptionsConfigurationInterface;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Library\Translations\TranslationTable;
use Solspace\Freeform\Services\Form\TranslationsService;

/**
 * Translation Manager fallback table for Freeform translations.
 *
 * @since 5.26.0
 */
class TmTranslationTable extends TranslationTable
{
    private const MISSING = '__translation_manager_missing__';

    private bool $fieldResolved = false;

    private ?object $field = null;

    public function __construct(
        private TranslationTable $nativeTable,
        private Form $form,
        private string $type,
        private string $namespace,
    ) {
        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    public function hasTranslations(): bool
    {
        return $this->nativeTable->hasTranslations() || $this->hasFallbackTranslations();
    }

    /**
     * @inheritdoc
     */
    public function get(string $path, mixed $defaultValue = null): mixed
    {
        $nativeValue = $this->nativeTable->get($path, self::MISSING);
        if ($nativeValue !== self::MISSING) {
            return $nativeValue;
        }

        if ($this->type !== TranslationsService::TYPE_FIELDS) {
            return $this->translateScalarDefault($defaultValue);
        }

        if ($path === 'optionConfiguration.options') {
            return $this->getTranslatedOptionConfigurationOptions();
        }

        return $this->translateScalarDefault($defaultValue);
    }

    private function hasFallbackTranslations(): bool
    {
        return $this->type !== TranslationsService::TYPE_FIELDS
            || $this->getField() !== null;
    }

    private function translateScalarDefault(mixed $defaultValue): mixed
    {
        if (!is_scalar($defaultValue)) {
            return $defaultValue;
        }

        $source = (string)$defaultValue;
        if ($source === '') {
            return $defaultValue;
        }

        return Craft::t('freeform', $source);
    }

    /**
     * @return array<int,object>|null
     */
    private function getTranslatedOptionConfigurationOptions(): ?array
    {
        $field = $this->getField();
        if ($field === null || !method_exists($field, 'getOptionConfiguration')) {
            return null;
        }

        $optionConfiguration = $field->getOptionConfiguration();
        if (!$optionConfiguration instanceof OptionsConfigurationInterface) {
            return null;
        }

        $configuration = $optionConfiguration->toArray();
        $options = $configuration['options'] ?? null;
        if (!is_array($options)) {
            return null;
        }

        $translatedOptions = [];
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }

            $translatedOptions[] = (object)[
                'value' => (string)($option['value'] ?? ''),
                'label' => $this->translateScalarDefault($option['label'] ?? ''),
                'optgroup' => (bool)($option['optgroup'] ?? false),
            ];
        }

        return $translatedOptions;
    }

    private function getField(): ?object
    {
        if ($this->fieldResolved) {
            return $this->field;
        }

        $this->fieldResolved = true;

        foreach ($this->form->getLayout()->getFields() as $field) {
            if (!method_exists($field, 'getUid')) {
                continue;
            }

            if ($field->getUid() === $this->namespace) {
                $this->field = $field;
                return $this->field;
            }
        }

        return null;
    }
}
