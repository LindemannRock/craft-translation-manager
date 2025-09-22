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
     * @var bool Track if hooks have been logged to prevent spam
     */
    private static bool $_hasLoggedHooks = false;

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
            $this->logInfo("FormieIntegration: Not available - Formie plugin not found or not enabled");
            return;
        }

        // Listen to form save events
        Event::on(
            \verbb\formie\elements\Form::class,
            \verbb\formie\elements\Form::EVENT_AFTER_SAVE,
            function (\craft\events\ModelEvent $event) {
                $this->logInfo("FormieIntegration: Form saved - " . $event->sender->handle);
                $this->handleFormSave($event->sender);
            }
        );

        // Listen to form delete events
        Event::on(
            \verbb\formie\elements\Form::class,
            \verbb\formie\elements\Form::EVENT_AFTER_DELETE,
            function (\craft\events\ModelEvent $event) {
                $this->logInfo("FormieIntegration: Form deleted - " . $event->sender->handle);
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

        // Capture page button labels
        foreach ($form->getPages() as $page) {
            $pageSettings = $page->getPageSettings();

            // Submit button label
            if ($pageSettings->submitButtonLabel ?? false) {
                $captured[] = $this->createTranslation(
                    $pageSettings->submitButtonLabel,
                    "formie.{$form->handle}.button.submit"
                );
            }

            // Back button label
            if ($pageSettings->backButtonLabel ?? false) {
                $captured[] = $this->createTranslation(
                    $pageSettings->backButtonLabel,
                    "formie.{$form->handle}.button.back"
                );
            }

            // Save button label
            if ($pageSettings->saveButtonLabel ?? false) {
                $captured[] = $this->createTranslation(
                    $pageSettings->saveButtonLabel,
                    "formie.{$form->handle}.button.save"
                );
            }
        }

        // Capture form messages (convert TipTap JSON to clean HTML)
        if ($form->settings->submitActionMessage ?? false) {
            $message = $this->convertTipTapToHtml($form->settings->submitActionMessage);
            $captured[] = $this->createTranslation(
                $message,
                "formie.{$form->handle}.message.submit"
            );
        }

        if ($form->settings->errorMessage ?? false) {
            $message = $this->convertTipTapToHtml($form->settings->errorMessage);
            $captured[] = $this->createTranslation(
                $message,
                "formie.{$form->handle}.message.error"
            );
        }


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

        if (property_exists($field, 'placeholder') && $field->placeholder) {
            $captured[] = $this->createTranslation(
                $field->placeholder,
                "formie.{$formHandle}.{$fieldHandle}.placeholder"
            );
        }

        if (property_exists($field, 'errorMessage') && $field->errorMessage) {
            $captured[] = $this->createTranslation(
                $field->errorMessage,
                "formie.{$formHandle}.{$fieldHandle}.error"
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

        // Log field processing at debug level for debugging
        $this->logDebug("Processing Formie field: {$fieldClass} ({$fieldHandle})");

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

            case 'verbb\formie\fields\Agree':
                // Use getDescriptionHtml() method to get the converted HTML
                if (method_exists($field, 'getDescriptionHtml')) {
                    $descriptionHtml = $field->getDescriptionHtml();
                    if (!empty($descriptionHtml)) {
                        $captured[] = $this->createTranslation(
                            (string)$descriptionHtml,
                            "formie.{$formHandle}.{$fieldHandle}.description"
                        );
                    }
                }
                if (property_exists($field, 'checkedValue') && $field->checkedValue) {
                    $captured[] = $this->createTranslation(
                        $field->checkedValue,
                        "formie.{$formHandle}.{$fieldHandle}.checkedValue"
                    );
                }
                if (property_exists($field, 'uncheckedValue') && $field->uncheckedValue) {
                    $captured[] = $this->createTranslation(
                        $field->uncheckedValue,
                        "formie.{$formHandle}.{$fieldHandle}.uncheckedValue"
                    );
                }
                break;

            // Address field with subfield labels
            case 'verbb\formie\fields\Address':
                $subfields = [
                    'address1' => ['enabled' => $field->address1Enabled ?? false],
                    'address2' => ['enabled' => $field->address2Enabled ?? false],
                    'address3' => ['enabled' => $field->address3Enabled ?? false],
                    'city' => ['enabled' => $field->cityEnabled ?? false],
                    'state' => ['enabled' => $field->stateEnabled ?? false],
                    'zip' => ['enabled' => $field->zipEnabled ?? false],
                    'country' => ['enabled' => $field->countryEnabled ?? false],
                ];

                foreach ($subfields as $subfield => $config) {
                    if ($config['enabled']) {
                        $labelProp = $subfield . 'Label';
                        if (property_exists($field, $labelProp) && $field->$labelProp) {
                            $captured[] = $this->createTranslation(
                                $field->$labelProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.label"
                            );
                        }

                        $placeholderProp = $subfield . 'Placeholder';
                        if (property_exists($field, $placeholderProp) && $field->$placeholderProp) {
                            $captured[] = $this->createTranslation(
                                $field->$placeholderProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.placeholder"
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
                            $captured[] = $this->createTranslation(
                                $field->$labelProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.label"
                            );
                        }

                        $placeholderProp = $subfield . 'Placeholder';
                        if (property_exists($field, $placeholderProp) && $field->$placeholderProp) {
                            $captured[] = $this->createTranslation(
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
                            $captured[] = $this->createTranslation(
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
                            $captured[] = $this->createTranslation(
                                $column['heading'],
                                "formie.{$formHandle}.{$fieldHandle}.column.{$col}"
                            );
                        }
                    }
                }

                if (property_exists($field, 'addRowLabel') && $field->addRowLabel) {
                    $captured[] = $this->createTranslation(
                        $field->addRowLabel,
                        "formie.{$formHandle}.{$fieldHandle}.addRowLabel"
                    );
                }
                break;

            // Repeater field
            case 'verbb\formie\fields\Repeater':
                if (property_exists($field, 'addLabel') && $field->addLabel) {
                    $captured[] = $this->createTranslation(
                        $field->addLabel,
                        "formie.{$formHandle}.{$fieldHandle}.addLabel"
                    );
                }

                if (property_exists($field, 'removeLabel') && $field->removeLabel) {
                    $captured[] = $this->createTranslation(
                        $field->removeLabel,
                        "formie.{$formHandle}.{$fieldHandle}.removeLabel"
                    );
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
                            $captured[] = $this->createTranslation(
                                $field->$labelProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.label"
                            );
                        }

                        $placeholderProp = $subfield . 'Placeholder';
                        if (property_exists($field, $placeholderProp) && $field->$placeholderProp) {
                            $captured[] = $this->createTranslation(
                                $field->$placeholderProp,
                                "formie.{$formHandle}.{$fieldHandle}.{$subfield}.placeholder"
                            );
                        }
                    }
                }
                break;

            // Custom Paragraph field
            case 'lindemannrock\formieparagraphfield\fields\Paragraph':
                if (property_exists($field, 'paragraphContent') && $field->paragraphContent) {
                    $captured[] = $this->createTranslation(
                        $field->paragraphContent,
                        "formie.{$formHandle}.{$fieldHandle}.content"
                    );
                }
                break;

            // Custom Rating field
            case 'lindemannrock\formieratingfield\fields\Rating':
                // Capture endpoint labels if enabled
                if (property_exists($field, 'showEndpointLabels') && $field->showEndpointLabels) {
                    if (property_exists($field, 'startLabel') && $field->startLabel) {
                        $captured[] = $this->createTranslation(
                            $field->startLabel,
                            "formie.{$formHandle}.{$fieldHandle}.startLabel"
                        );
                    }

                    if (property_exists($field, 'endLabel') && $field->endLabel) {
                        $captured[] = $this->createTranslation(
                            $field->endLabel,
                            "formie.{$formHandle}.{$fieldHandle}.endLabel"
                        );
                    }
                }

                // Capture custom labels for rating values if they exist
                if (property_exists($field, 'customLabels') && is_array($field->customLabels)) {
                    foreach ($field->customLabels as $value => $label) {
                        if (!empty($label)) {
                            $captured[] = $this->createTranslation(
                                $label,
                                "formie.{$formHandle}.{$fieldHandle}.customLabel.{$value}"
                            );
                        }
                    }
                }
                break;

            // Section field
            case 'verbb\formie\fields\Section':
                if (property_exists($field, 'sectionText') && $field->sectionText) {
                    $captured[] = $this->createTranslation(
                        $field->sectionText,
                        "formie.{$formHandle}.{$fieldHandle}.text"
                    );
                }
                break;

            // Summary field
            case 'verbb\formie\fields\Summary':
                if (property_exists($field, 'summaryText') && $field->summaryText) {
                    $captured[] = $this->createTranslation(
                        $field->summaryText,
                        "formie.{$formHandle}.{$fieldHandle}.text"
                    );
                }
                break;

            // File Upload field
            case 'verbb\formie\fields\FileUpload':
                if (property_exists($field, 'uploadLocationText') && $field->uploadLocationText) {
                    $captured[] = $this->createTranslation(
                        $field->uploadLocationText,
                        "formie.{$formHandle}.{$fieldHandle}.uploadText"
                    );
                }
                if (property_exists($field, 'allowedKinds') && is_array($field->allowedKinds)) {
                    foreach ($field->allowedKinds as $kind) {
                        if (!empty($kind)) {
                            $captured[] = $this->createTranslation(
                                $kind,
                                "formie.{$formHandle}.{$fieldHandle}.allowedKind.{$kind}"
                            );
                        }
                    }
                }
                break;

            // Payment field
            case 'verbb\formie\fields\Payment':
                if (property_exists($field, 'currency') && $field->currency) {
                    $captured[] = $this->createTranslation(
                        $field->currency,
                        "formie.{$formHandle}.{$fieldHandle}.currency"
                    );
                }
                if (property_exists($field, 'paymentMethodLabel') && $field->paymentMethodLabel) {
                    $captured[] = $this->createTranslation(
                        $field->paymentMethodLabel,
                        "formie.{$formHandle}.{$fieldHandle}.methodLabel"
                    );
                }
                break;

            // Phone field
            case 'verbb\formie\fields\Phone':
                if (property_exists($field, 'countryLabel') && $field->countryLabel) {
                    $captured[] = $this->createTranslation(
                        $field->countryLabel,
                        "formie.{$formHandle}.{$fieldHandle}.countryLabel"
                    );
                }
                if (property_exists($field, 'numberLabel') && $field->numberLabel) {
                    $captured[] = $this->createTranslation(
                        $field->numberLabel,
                        "formie.{$formHandle}.{$fieldHandle}.numberLabel"
                    );
                }
                break;

            // Password field
            case 'verbb\formie\fields\Password':
                if (property_exists($field, 'confirmationLabel') && $field->confirmationLabel) {
                    $captured[] = $this->createTranslation(
                        $field->confirmationLabel,
                        "formie.{$formHandle}.{$fieldHandle}.confirmationLabel"
                    );
                }
                break;

            // Number field
            case 'verbb\formie\fields\Number':
                if (property_exists($field, 'minLabel') && $field->minLabel) {
                    $captured[] = $this->createTranslation(
                        $field->minLabel,
                        "formie.{$formHandle}.{$fieldHandle}.minLabel"
                    );
                }
                if (property_exists($field, 'maxLabel') && $field->maxLabel) {
                    $captured[] = $this->createTranslation(
                        $field->maxLabel,
                        "formie.{$formHandle}.{$fieldHandle}.maxLabel"
                    );
                }
                if (property_exists($field, 'unitText') && $field->unitText) {
                    $captured[] = $this->createTranslation(
                        $field->unitText,
                        "formie.{$formHandle}.{$fieldHandle}.unitText"
                    );
                }
                break;

            // Signature field
            case 'verbb\formie\fields\Signature':
                if (property_exists($field, 'clearLabel') && $field->clearLabel) {
                    $captured[] = $this->createTranslation(
                        $field->clearLabel,
                        "formie.{$formHandle}.{$fieldHandle}.clearLabel"
                    );
                }
                if (property_exists($field, 'submitLabel') && $field->submitLabel) {
                    $captured[] = $this->createTranslation(
                        $field->submitLabel,
                        "formie.{$formHandle}.{$fieldHandle}.submitLabel"
                    );
                }
                break;

            // Calculations field
            case 'verbb\formie\fields\Calculations':
                if (property_exists($field, 'calculationLabel') && $field->calculationLabel) {
                    $captured[] = $this->createTranslation(
                        $field->calculationLabel,
                        "formie.{$formHandle}.{$fieldHandle}.calculationLabel"
                    );
                }
                break;

            // Group field - process nested fields
            case 'verbb\formie\fields\Group':
                if (method_exists($field, 'getCustomFields')) {
                    foreach ($field->getCustomFields() as $nestedField) {
                        $nestedTranslations = $this->captureFieldTranslations($form, $nestedField);
                        $captured = array_merge($captured, $nestedTranslations);
                    }
                }
                break;
        }

        return array_filter($captured);
    }

    /**
     * Convert TipTap JSON to clean HTML
     */
    private function convertTipTapToHtml($content): string
    {
        if (empty($content)) {
            return '';
        }

        // If it's already HTML, return as-is
        if (!is_string($content) || $content[0] !== '[') {
            return $content;
        }

        try {
            $data = json_decode($content, true);
            if (!is_array($data)) {
                return $content;
            }

            $htmlParts = [];

            // Convert TipTap blocks to HTML
            foreach ($data as $block) {
                if (isset($block['type']) && $block['type'] === 'paragraph') {
                    $paragraphText = '';

                    if (isset($block['content']) && is_array($block['content'])) {
                        foreach ($block['content'] as $textNode) {
                            if (isset($textNode['type']) && $textNode['type'] === 'text' && isset($textNode['text'])) {
                                $paragraphText .= $textNode['text'];
                            }
                        }
                    }

                    // Add all paragraphs (including empty ones for spacing)
                    if (!empty(trim($paragraphText))) {
                        $htmlParts[] = '<p>' . htmlspecialchars($paragraphText) . '</p>';
                    } else {
                        // Empty paragraph = intentional line break/spacing
                        $htmlParts[] = '<p></p>';
                    }
                }
            }

            return implode('', $htmlParts);

        } catch (\Exception) {
            // If JSON parsing fails, return original
            return $content;
        }
    }

}
