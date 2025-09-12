<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Formie plugin integration
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\integrations;

use Craft;
use craft\helpers\StringHelper;
use yii\base\Event;

/**
 * Formie Integration
 * 
 * Handles translation capture and management for Formie forms
 */
class FormieIntegration extends BaseIntegration
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'formie';
    }

    /**
     * @inheritdoc
     */
    public function getPluginHandle(): string
    {
        return 'formie';
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return class_exists('verbb\formie\Formie') && 
               Craft::$app->getPlugins()->isPluginInstalled('formie') &&
               Craft::$app->getPlugins()->isPluginEnabled('formie');
    }

    /**
     * @inheritdoc
     */
    public function registerHooks(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        // Listen to form save events
        Event::on(
            \verbb\formie\elements\Form::class,
            \verbb\formie\elements\Form::EVENT_AFTER_SAVE,
            function (\craft\events\ModelEvent $event) {
                $this->handleFormSave($event->sender);
            }
        );

        // Listen to form delete events  
        Event::on(
            \verbb\formie\elements\Form::class,
            \verbb\formie\elements\Form::EVENT_AFTER_DELETE,
            function (\craft\events\ModelEvent $event) {
                $this->handleFormDelete($event->sender);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function captureTranslations($element): array
    {
        if (!$element instanceof \verbb\formie\elements\Form) {
            return [];
        }

        $captured = [];
        $form = $element;

        // Capture form title
        if ($form->title) {
            $captured[] = $this->createTranslation(
                $form->title,
                "formie.{$form->handle}.title"
            );
        }

        // Capture submit button text
        $submitButtonText = $form->settings->submitButtonLabel ?? 'Submit';
        $captured[] = $this->createTranslation(
            $submitButtonText,
            "formie.{$form->handle}.button.submit"
        );

        // Capture all form fields
        foreach ($form->getCustomFields() as $field) {
            $fieldTranslations = $this->captureFieldTranslations($form, $field);
            $captured = array_merge($captured, $fieldTranslations);
        }

        return array_filter($captured); // Remove null entries
    }

    /**
     * @inheritdoc
     */
    public function checkUsage(): void
    {
        $this->logInfo('Checking Formie translation usage');
        
        // Use the existing working logic with includeUsageCheck
        $translations = $this->getTranslationsService()->getTranslations([
            'type' => 'forms',
            'includeUsageCheck' => true
        ]);
        
        $this->logInfo('Checked ' . count($translations) . ' Formie translations for usage');
    }

    /**
     * @inheritdoc
     */
    public function getSupportedContentTypes(): array
    {
        return [
            'forms' => 'Formie Forms',
            'fields' => 'Form Fields',
            'options' => 'Field Options',
            'buttons' => 'Form Buttons',
            'messages' => 'Form Messages'
        ];
    }

    /**
     * @inheritdoc
     */
    public function getConfigSchema(): array
    {
        return array_merge(parent::getConfigSchema(), [
            'captureFormMessages' => [
                'type' => 'boolean',
                'label' => 'Capture Form Messages',
                'instructions' => 'Capture submit/error messages from forms',
                'default' => true,
            ],
            'captureFieldOptions' => [
                'type' => 'boolean', 
                'label' => 'Capture Field Options',
                'instructions' => 'Capture dropdown/radio button options',
                'default' => true,
            ],
            'autoMarkUnused' => [
                'type' => 'boolean',
                'label' => 'Auto-mark Unused Translations',
                'instructions' => 'Automatically mark translations as unused when forms are deleted',
                'default' => true,
            ]
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function getTranslationType(): string
    {
        return 'forms';
    }

    /**
     * Handle form save event
     */
    private function handleFormSave(\verbb\formie\elements\Form $form): void
    {
        $this->logInfo("Processing form save for: {$form->handle}");
        
        // Capture translations from the saved form
        $captured = $this->captureTranslations($form);
        
        $this->logInfo("Captured " . count($captured) . " translations from form {$form->handle}");

        // Check for unused translations
        $this->checkUsage();
    }

    /**
     * Handle form delete event
     */
    private function handleFormDelete(\verbb\formie\elements\Form $form): void
    {
        $this->logInfo("Processing form deletion for: {$form->handle}");
        
        // Mark all translations for this form as unused
        $translations = $this->getTranslationsService()->getTranslations([
            'type' => 'forms',
            'search' => "formie.{$form->handle}."
        ]);

        $translationIds = array_column($translations, 'id');
        $marked = $this->markTranslationsUnused($translationIds);
        
        $this->logInfo("Marked {$marked} translations as unused after form deletion");
    }

    /**
     * Capture translations from a form field
     * 
     * @param \verbb\formie\elements\Form $form
     * @param mixed $field
     * @return array Captured translations
     */
    private function captureFieldTranslations($form, $field): array
    {
        $captured = [];
        $formHandle = $form->handle;
        $fieldHandle = $field->handle;

        // Capture basic field properties
        if ($field->label) {
            $captured[] = $this->createTranslation(
                $field->label,
                "formie.{$formHandle}.{$fieldHandle}.label"
            );
        }

        if ($field->instructions) {
            $captured[] = $this->createTranslation(
                $field->instructions,
                "formie.{$formHandle}.{$fieldHandle}.instructions"
            );
        }

        if ($field->placeholder ?? false) {
            $captured[] = $this->createTranslation(
                $field->placeholder,
                "formie.{$formHandle}.{$fieldHandle}.placeholder"
            );
        }

        // Handle field type-specific translations
        $fieldTypeTranslations = $this->captureFieldTypeSpecificTranslations($form, $field);
        $captured = array_merge($captured, $fieldTypeTranslations);

        return array_filter($captured);
    }

    /**
     * Capture field type-specific translations
     * 
     * @param \verbb\formie\elements\Form $form
     * @param mixed $field
     * @return array Captured translations
     */
    private function captureFieldTypeSpecificTranslations($form, $field): array
    {
        $captured = [];
        $fieldClass = get_class($field);
        $formHandle = $form->handle;
        $fieldHandle = $field->handle;

        switch ($fieldClass) {
            case 'verbb\formie\fields\Dropdown':
            case 'verbb\formie\fields\Radio':
            case 'verbb\formie\fields\Checkboxes':
                if (property_exists($field, 'options') && is_array($field->options)) {
                    foreach ($field->options as $option) {
                        if (isset($option['label']) && !empty($option['label'])) {
                            $optionValue = $option['value'] ?? StringHelper::toKebabCase($option['label']);
                            $captured[] = $this->createTranslation(
                                $option['label'],
                                "formie.{$formHandle}.{$fieldHandle}.option.{$optionValue}"
                            );
                        }
                    }
                }
                break;

            case 'verbb\formie\fields\Html':
                if (property_exists($field, 'htmlContent') && $field->htmlContent) {
                    $captured[] = $this->createTranslation(
                        $field->htmlContent,
                        "formie.{$formHandle}.{$fieldHandle}.content"
                    );
                }
                break;

            case 'verbb\formie\fields\Heading':
                if (property_exists($field, 'headingText') && $field->headingText) {
                    $captured[] = $this->createTranslation(
                        $field->headingText,
                        "formie.{$formHandle}.{$fieldHandle}.text"
                    );
                }
                break;

            // Add more field types as needed
        }

        return array_filter($captured);
    }
}