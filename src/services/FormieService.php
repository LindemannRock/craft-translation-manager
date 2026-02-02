<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for capturing and managing Formie form translations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use craft\base\Component;
use craft\helpers\StringHelper;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\TranslationManager;
use yii\base\Event;

/**
 * Formie Integration Service
 *
 * @since 1.0.0
 */
class FormieService extends Component
{
    use LoggingTrait;

    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(TranslationManager::$plugin->id);
    }

    /**
     * Register Formie event hooks
     *
     * @since 1.0.0
     */
    public function registerFormieHooks(): void
    {
        // Listen to form save events
        Event::on(
            \verbb\formie\elements\Form::class,
            \verbb\formie\elements\Form::EVENT_AFTER_SAVE,
            function(\craft\events\ModelEvent $event) {
                $this->captureFormTranslations($event->sender);
                
                // After capturing, run a usage check to mark unused translations
                $this->checkFormUsage();
            }
        );
    }

    /**
     * Capture all translatable text from a Formie form
     *
     * @since 1.0.0
     */
    public function captureFormTranslations($form): void
    {
        if (!$form) {
            return;
        }

        // Check if form handle or title is excluded by pattern
        if ($this->isFormExcluded($form->handle, $form->title)) {
            $this->logInfo("Skipping excluded form", [
                'handle' => $form->handle,
                'title' => $form->title,
            ]);
            return;
        }

        $translationsService = TranslationManager::getInstance()->translations;

        // Capture form title
        if ($form->title) {
            $translationsService->createOrUpdateTranslation(
                $form->title,
                "formie.{$form->handle}.title"
            );
        }

        // Capture submit button text
        $submitButtonText = $form->settings->submitButtonLabel ?? 'Submit';
        $translationsService->createOrUpdateTranslation(
            $submitButtonText,
            "formie.{$form->handle}.button.submit"
        );

        // Capture back button text if multi-page
        if ($form->settings->backButtonLabel ?? false) {
            $translationsService->createOrUpdateTranslation(
                $form->settings->backButtonLabel,
                "formie.{$form->handle}.button.back"
            );
        }
        
        // Capture submission message - use getter which returns HTML
        if (method_exists($form->settings, 'getSubmitActionMessage')) {
            $htmlMessage = $form->settings->getSubmitActionMessage();
            if ($htmlMessage) {
                // Capture the HTML version as that's what Formie uses for translation
                $translationsService->createOrUpdateTranslation(
                    $htmlMessage,
                    "formie.submit"
                );
            }
        }
        
        // Capture error message - use getter which returns HTML
        if (method_exists($form->settings, 'getErrorMessage')) {
            $htmlMessage = $form->settings->getErrorMessage();
            if ($htmlMessage) {
                // Capture the HTML version as that's what Formie uses for translation
                $translationsService->createOrUpdateTranslation(
                    $htmlMessage,
                    "formie.error"
                );
            }
        }
        
        // Capture page submission messages (for multi-page forms)
        if ($form->settings->submitActionFormHide ?? false) {
            // This is the message shown when form is hidden after submission
            if ($form->settings->submitActionMessageHtml ?? false) {
                $translationsService->createOrUpdateTranslation(
                    $form->settings->submitActionMessageHtml,
                    "formie.{$form->handle}.submitMessageHtml"
                );
            }
        }

        // Capture field translations
        foreach ($form->getCustomFields() as $field) {
            $this->captureFieldTranslations($form, $field);
        }

        // NOTE: File export removed from here. Files should only be generated when
        // translations are actually modified in the CP, not when forms are saved.
        // This prevents unnecessary file regeneration and git changes.

        $this->logInfo("Captured translations for form", ['handle' => $form->handle]);
    }

    /**
     * Capture translations for a single field
     */
    private function captureFieldTranslations($form, $field): void
    {
        $translationsService = TranslationManager::getInstance()->translations;
        $formHandle = $form->handle;
        $fieldHandle = $field->handle;

        // Field label (always present) - use 'label' instead of deprecated 'name'
        if ($field->label) {
            $translationsService->createOrUpdateTranslation(
                $field->label,
                "formie.{$formHandle}.{$fieldHandle}.label"
            );
        }

        // Field instructions
        if ($field->instructions) {
            $translationsService->createOrUpdateTranslation(
                $field->instructions,
                "formie.{$formHandle}.{$fieldHandle}.instructions"
            );
        }

        // Placeholder
        if (property_exists($field, 'placeholder') && $field->placeholder) {
            $translationsService->createOrUpdateTranslation(
                $field->placeholder,
                "formie.{$formHandle}.{$fieldHandle}.placeholder"
            );
        }

        // Error message
        if (property_exists($field, 'errorMessage') && $field->errorMessage) {
            $translationsService->createOrUpdateTranslation(
                $field->errorMessage,
                "formie.{$formHandle}.{$fieldHandle}.error"
            );
        }

        // Handle field-specific translations
        $fieldClass = get_class($field);
        $this->logDebug("Processing field type", [
            'fieldClass' => $fieldClass,
            'handle' => $fieldHandle,
            'hasOptions' => property_exists($field, 'options'),
        ]);
        
        switch ($fieldClass) {
            // Fields with options
            case 'verbb\formie\fields\Dropdown':
            case 'verbb\formie\fields\Radio':
            case 'verbb\formie\fields\Checkboxes':
            case 'verbb\formie\fields\Categories':
            case 'verbb\formie\fields\Entries':
            case 'verbb\formie\fields\Products':
            case 'verbb\formie\fields\Tags':
            case 'verbb\formie\fields\Users':
            case 'verbb\formie\fields\Variants':
                if (property_exists($field, 'options') && is_array($field->options)) {
                    $this->logDebug("Found options for field", [
                        'fieldHandle' => $fieldHandle,
                        'optionCount' => count($field->options),
                    ]);

                    foreach ($field->options as $index => $option) {
                        $this->logDebug("Processing option", [
                            'index' => $index,
                            'option' => $option,
                        ]);

                        if (isset($option['label']) && !empty($option['label'])) {
                            $optionValue = $option['value'] ?? StringHelper::toKebabCase($option['label']);
                            $translationsService->createOrUpdateTranslation(
                                $option['label'],
                                "formie.{$formHandle}.{$fieldHandle}.option.{$optionValue}"
                            );
                            $this->logDebug("Captured option", ['label' => $option['label']]);
                        }
                    }
                } else {
                    $this->logDebug("No options property found", ['fieldHandle' => $fieldHandle]);
                }
                break;

            case 'verbb\formie\fields\Agree':
                // Use descriptionHtml which renders the rich text array to HTML string
                $descriptionHtml = $field->descriptionHtml ?? null;
                if ($descriptionHtml) {
                    $translationsService->createOrUpdateTranslation(
                        (string)$descriptionHtml,
                        "formie.{$formHandle}.{$fieldHandle}.description"
                    );
                }
                if (property_exists($field, 'checkedValue') && $field->checkedValue) {
                    $translationsService->createOrUpdateTranslation(
                        $field->checkedValue,
                        "formie.{$formHandle}.{$fieldHandle}.checkedValue"
                    );
                }
                if (property_exists($field, 'uncheckedValue') && $field->uncheckedValue) {
                    $translationsService->createOrUpdateTranslation(
                        $field->uncheckedValue,
                        "formie.{$formHandle}.{$fieldHandle}.uncheckedValue"
                    );
                }
                break;
            
            // Address field with subfield labels
            case 'verbb\formie\fields\Address':
                // Address has subfields with labels
                $subfields = [
                    'address1' => ['label' => 'Address 1', 'enabled' => $field->address1Enabled ?? false],
                    'address2' => ['label' => 'Address 2', 'enabled' => $field->address2Enabled ?? false],
                    'address3' => ['label' => 'Address 3', 'enabled' => $field->address3Enabled ?? false],
                    'city' => ['label' => 'City', 'enabled' => $field->cityEnabled ?? false],
                    'state' => ['label' => 'State/Province', 'enabled' => $field->stateEnabled ?? false],
                    'zip' => ['label' => 'ZIP/Postal Code', 'enabled' => $field->zipEnabled ?? false],
                    'country' => ['label' => 'Country', 'enabled' => $field->countryEnabled ?? false],
                ];
                
                foreach ($subfields as $subfield => $config) {
                    if ($config['enabled']) {
                        $labelProp = $subfield . 'Label';
                        if (property_exists($field, $labelProp) && $field->$labelProp) {
                            $translationsService->createOrUpdateTranslation(
                                $field->$labelProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.label"
                            );
                        }
                        
                        $placeholderProp = $subfield . 'Placeholder';
                        if (property_exists($field, $placeholderProp) && $field->$placeholderProp) {
                            $translationsService->createOrUpdateTranslation(
                                $field->$placeholderProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.placeholder"
                            );
                        }
                    }
                }
                break;
            
            // Name field with subfield labels
            case 'verbb\formie\fields\Name':
                $nameSubfields = [
                    'prefix' => $field->prefixEnabled ?? false,
                    'firstName' => true, // Always enabled
                    'middleName' => $field->middleNameEnabled ?? false,
                    'lastName' => $field->lastNameEnabled ?? false,
                ];
                
                foreach ($nameSubfields as $subfield => $enabled) {
                    if ($enabled) {
                        $labelProp = $subfield . 'Label';
                        if (property_exists($field, $labelProp) && $field->$labelProp) {
                            $translationsService->createOrUpdateTranslation(
                                $field->$labelProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.label"
                            );
                        }
                        
                        $placeholderProp = $subfield . 'Placeholder';
                        if (property_exists($field, $placeholderProp) && $field->$placeholderProp) {
                            $translationsService->createOrUpdateTranslation(
                                $field->$placeholderProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.placeholder"
                            );
                        }
                    }
                }
                break;
            
            // Recipients field with custom options
            case 'verbb\formie\fields\Recipients':
                if (property_exists($field, 'sources') && is_array($field->sources)) {
                    foreach ($field->sources as $source) {
                        if (is_array($source) && isset($source['label'])) {
                            $translationsService->createOrUpdateTranslation(
                                $source['label'],
                                "formie.{$formHandle}.{$fieldHandle}.recipient"
                            );
                        }
                    }
                }
                break;
            
            // Table field with column headers
            case 'verbb\formie\fields\Table':
                if (property_exists($field, 'columns') && is_array($field->columns)) {
                    foreach ($field->columns as $col => $column) {
                        if (isset($column['heading'])) {
                            $translationsService->createOrUpdateTranslation(
                                $column['heading'],
                                "formie.{$formHandle}.{$fieldHandle}.column.{$col}"
                            );
                        }
                    }
                }
                
                if (property_exists($field, 'addRowLabel') && $field->addRowLabel) {
                    $translationsService->createOrUpdateTranslation(
                        $field->addRowLabel,
                        "formie.{$formHandle}.{$fieldHandle}.addRowLabel"
                    );
                }
                break;
            
            // Repeater field
            case 'verbb\formie\fields\Repeater':
                if (property_exists($field, 'addLabel') && $field->addLabel) {
                    $translationsService->createOrUpdateTranslation(
                        $field->addLabel,
                        "formie.{$formHandle}.{$fieldHandle}.addLabel"
                    );
                }
                
                if (property_exists($field, 'removeLabel') && $field->removeLabel) {
                    $translationsService->createOrUpdateTranslation(
                        $field->removeLabel,
                        "formie.{$formHandle}.{$fieldHandle}.removeLabel"
                    );
                }
                break;
            
            // Heading field
            case 'verbb\formie\fields\Heading':
                if (property_exists($field, 'headingText') && $field->headingText) {
                    $translationsService->createOrUpdateTranslation(
                        $field->headingText,
                        "formie.{$formHandle}.{$fieldHandle}.text"
                    );
                }
                break;
            
            // Html field
            case 'verbb\formie\fields\Html':
                if (property_exists($field, 'htmlContent') && $field->htmlContent) {
                    $this->logDebug("HTML field found", [
                        'fieldHandle' => $fieldHandle,
                        'contentPreview' => substr($field->htmlContent, 0, 50),
                    ]);

                    // Always capture HTML content - the Twig check will be done in createOrUpdateTranslation
                    $translationsService->createOrUpdateTranslation(
                        $field->htmlContent,
                        "formie.{$formHandle}.{$fieldHandle}.content"
                    );
                } else {
                    $this->logDebug("HTML field has no content or property missing", ['fieldHandle' => $fieldHandle]);
                }
                break;
            
            // Paragraph field
            case 'lindemannrock\modules\formieparagraphfield\fields\Paragraph':
                if (property_exists($field, 'paragraphContent') && $field->paragraphContent) {
                    $this->logDebug("Paragraph field found", [
                        'fieldHandle' => $fieldHandle,
                        'contentPreview' => substr($field->paragraphContent, 0, 50),
                    ]);

                    // Always capture paragraph content - the Twig check will be done in createOrUpdateTranslation
                    $translationsService->createOrUpdateTranslation(
                        $field->paragraphContent,
                        "formie.{$formHandle}.{$fieldHandle}.content"
                    );
                } else {
                    $this->logDebug("Paragraph field has no content or property missing", ['fieldHandle' => $fieldHandle]);
                }
                break;
            
            // Rating field
            case 'lindemannrock\modules\formieratingfield\fields\Rating':
                // Capture endpoint labels if enabled
                if (property_exists($field, 'showEndpointLabels') && $field->showEndpointLabels) {
                    if (property_exists($field, 'startLabel') && $field->startLabel) {
                        $translationsService->createOrUpdateTranslation(
                            $field->startLabel,
                            "formie.{$formHandle}.{$fieldHandle}.startLabel"
                        );
                    }
                    
                    if (property_exists($field, 'endLabel') && $field->endLabel) {
                        $translationsService->createOrUpdateTranslation(
                            $field->endLabel,
                            "formie.{$formHandle}.{$fieldHandle}.endLabel"
                        );
                    }
                }
                
                // Capture custom labels for rating values if they exist
                if (property_exists($field, 'customLabels') && is_array($field->customLabels)) {
                    foreach ($field->customLabels as $value => $label) {
                        if (!empty($label)) {
                            $translationsService->createOrUpdateTranslation(
                                $label,
                                "formie.{$formHandle}.{$fieldHandle}.customLabel.{$value}"
                            );
                        }
                    }
                }
                break;
            
            // Date field with subfield labels
            case 'verbb\formie\fields\Date':
                $dateSubfields = [
                    'day' => $field->dayEnabled ?? false,
                    'month' => $field->monthEnabled ?? false,
                    'year' => $field->yearEnabled ?? false,
                    'hour' => $field->hourEnabled ?? false,
                    'minute' => $field->minuteEnabled ?? false,
                    'second' => $field->secondEnabled ?? false,
                    'ampm' => $field->ampmEnabled ?? false,
                ];
                
                foreach ($dateSubfields as $subfield => $enabled) {
                    if ($enabled) {
                        $labelProp = $subfield . 'Label';
                        if (property_exists($field, $labelProp) && $field->$labelProp) {
                            $translationsService->createOrUpdateTranslation(
                                $field->$labelProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.label"
                            );
                        }
                        
                        $placeholderProp = $subfield . 'Placeholder';
                        if (property_exists($field, $placeholderProp) && $field->$placeholderProp) {
                            $translationsService->createOrUpdateTranslation(
                                $field->$placeholderProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.placeholder"
                            );
                        }
                    }
                }
                break;
            
            // Group field - process nested fields
            case 'verbb\formie\fields\Group':
                if (method_exists($field, 'getCustomFields')) {
                    foreach ($field->getCustomFields() as $nestedField) {
                        $this->captureFieldTranslations($form, $nestedField);
                    }
                }
                break;
        }
    }

    /**
     * Get all active form handles
     *
     * @since 1.0.0
     */
    public function getActiveFormHandles(): array
    {
        if (!PluginHelper::isPluginEnabled('formie')) {
            return [];
        }

        $forms = \verbb\formie\Formie::getInstance()->getForms()->getAllForms();
        $handles = [];

        foreach ($forms as $form) {
            $handles[] = $form->handle;
        }

        return $handles;
    }
    
    /**
     * Check form usage and mark unused translations
     *
     * @since 1.0.0
     */
    public function checkFormUsage(): void
    {
        $this->logInfo('Running form usage check after form save');

        // Get all Formie translations and check their usage
        $translations = TranslationManager::getInstance()->translations->getTranslations([
            'type' => 'forms',
            'includeUsageCheck' => true,
        ]);

        $this->logInfo('Checked form translations for usage', ['count' => count($translations)]);
    }

    /**
     * Check if a form should be excluded based on patterns
     * Checks both form handle and title against exclusion patterns
     *
     * @param string $formHandle The form handle to check
     * @param string|null $formTitle The form title/name to check (optional)
     * @return bool True if form should be excluded
     */
    private function isFormExcluded(string $formHandle, ?string $formTitle = null): bool
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $patterns = $settings->excludeFormHandlePatterns ?? [];

        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);
            if (empty($pattern)) {
                continue;
            }

            // Case-insensitive check if handle contains the pattern
            if (stripos($formHandle, $pattern) !== false) {
                $this->logInfo("Form excluded by handle pattern", [
                    'handle' => $formHandle,
                    'title' => $formTitle,
                    'pattern' => $pattern,
                    'matchedIn' => 'handle',
                ]);
                return true;
            }

            // Also check form title if provided
            if ($formTitle !== null && stripos($formTitle, $pattern) !== false) {
                $this->logInfo("Form excluded by title pattern", [
                    'handle' => $formHandle,
                    'title' => $formTitle,
                    'pattern' => $pattern,
                    'matchedIn' => 'title',
                ]);
                return true;
            }
        }

        return false;
    }
}
