<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Formie plugin integration
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\integrations;

use lindemannrock\base\helpers\PluginHelper;
use yii\base\Event;

/**
 * Formie Integration
 *
 * Handles translation capture and management for Formie forms
 *
 * @since 1.5.0
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
        return PluginHelper::isPluginEnabled('formie');
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
            function(\craft\events\ModelEvent $event) {
                $this->logInfo("FormieIntegration: Form saved", ['handle' => $event->sender->handle]);
                $this->handleFormSave($event->sender);
            }
        );

        // Listen to form delete events via the Elements SERVICE, not the Form
        // element's own EVENT_AFTER_DELETE. Formie's Form::afterDelete() overrides
        // the base method without calling parent::afterDelete(), so the element's
        // EVENT_AFTER_DELETE never fires (even though Formie documents it). The
        // service-level EVENT_AFTER_DELETE_ELEMENT is triggered by Craft itself
        // for every element delete (soft or hard), independent of that override.
        Event::on(
            \craft\services\Elements::class,
            \craft\services\Elements::EVENT_AFTER_DELETE_ELEMENT,
            function(\craft\events\ElementEvent $event) {
                if ($event->element instanceof \verbb\formie\elements\Form) {
                    $this->logInfo("FormieIntegration: Form deleted", ['handle' => $event->element->handle]);
                    $this->handleFormDelete($event->element);
                }
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

        $form = $element;

        // Check if form handle or title is excluded by pattern
        if ($this->isFormExcluded($form->handle, $form->title)) {
            $this->logInfo("Skipping excluded form", [
                'handle' => $form->handle,
                'title' => $form->title,
            ]);
            return [];
        }

        $captured = $this->captureDefaultTranslations();

        foreach ($this->collectTranslationEntries($form) as $entry) {
            $captured[] = $this->createTranslation($entry['text'], $entry['context']);
        }

        return array_filter($captured); // Remove null entries
    }

    /**
     * @inheritdoc
     */
    public function checkUsage(): void
    {
        // Builds the active-text map from every Formie form, batches
        // status updates for newly-unused / re-activated rows. Runs
        // synchronously — fast enough on the form-save and "Rescan all
        // forms" paths (the only callers).
        $result = $this->getTranslationsService()->recheckUsage();

        $this->logInfo('Checked Formie translation usage', $result);
    }

    /**
     * @inheritdoc
     */
    public function captureAll(): array
    {
        $processed = 0;
        $captured = 0;

        if (!$this->isAvailable()) {
            return ['processed' => 0, 'captured' => 0];
        }

        foreach (\verbb\formie\Formie::getInstance()->getForms()->getAllForms() as $form) {
            $captured += count($this->captureTranslations($form));
            $processed++;
        }

        $this->checkUsage();

        return ['processed' => $processed, 'captured' => $captured];
    }

    /**
     * @inheritdoc
     */
    public function getActiveTranslationTexts(): array
    {
        $activeTexts = [];
        $forms = \verbb\formie\Formie::getInstance()->getForms()->getAllForms();

        foreach ($forms as $form) {
            if ($this->isFormExcluded($form->handle, $form->title)) {
                continue;
            }

            foreach ($this->collectTranslationEntries($form) as $entry) {
                $activeTexts[$entry['text']] = true;
            }
        }

        $this->logDebug('Collected Formie active texts', ['count' => count($activeTexts)]);

        return $activeTexts;
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
            'messages' => 'Form Messages',
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
            ],
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
        $this->logInfo("Processing form save", ['handle' => $form->handle]);

        // Capture translations from the saved form
        $captured = $this->captureTranslations($form);

        $this->logInfo("Captured translations from form", [
            'handle' => $form->handle,
            'count' => count($captured),
        ]);

        // Check for unused translations
        $this->checkUsage();
    }

    /**
     * Handle form delete event
     */
    private function handleFormDelete(\verbb\formie\elements\Form $form): void
    {
        $this->logInfo("Processing form deletion", ['handle' => $form->handle]);

        // Mark all translations for this form as unused — across every language.
        // Without allSites, getTranslations() defaults to the current CP site
        // language, so other languages' rows for the deleted form would never be
        // marked unused (they only self-healed later via recheckUsage on the next
        // form save).
        $translations = $this->getTranslationsService()->getTranslations([
            'type' => 'forms',
            'search' => "formie.{$form->handle}.",
            'allSites' => true,
        ]);

        $translationIds = array_column($translations, 'id');
        $marked = $this->markTranslationsUnused($translationIds);

        $this->logInfo("Marked translations as unused after form deletion", ['marked' => $marked]);
    }

    /**
     * Collect source strings from a Formie form without mutating storage.
     *
     * @return array<int,array{text:string,context:string}>
     */
    private function collectTranslationEntries(\verbb\formie\elements\Form $form): array
    {
        $entries = [];
        $formHandle = $form->handle;

        $this->addEntry($entries, $form->title, "formie.{$formHandle}.title");

        $pages = $form->getPages();
        $hasMultiplePages = count($pages) > 1;

        foreach ($pages as $index => $page) {
            $pageSettings = $page->getPageSettings();
            $buttonContext = $this->buildPageButtonContext($formHandle, $index, $hasMultiplePages);

            $this->addEntry($entries, $pageSettings->submitButtonLabel ?? null, "{$buttonContext}.submit");
            $this->addEntry($entries, $pageSettings->backButtonLabel ?? null, "{$buttonContext}.back");
            $this->addEntry($entries, $pageSettings->saveButtonLabel ?? null, "{$buttonContext}.save");
        }

        // IMPORTANT: read RAW settings — getters can return translated content.
        if ($form->settings->submitActionMessage ?? false) {
            $this->addEntry(
                $entries,
                $this->convertTipTapToHtml($form->settings->submitActionMessage),
                "formie.{$formHandle}.message.submit"
            );
        }

        if ($form->settings->errorMessage ?? false) {
            $this->addEntry(
                $entries,
                $this->convertTipTapToHtml($form->settings->errorMessage),
                "formie.{$formHandle}.message.error"
            );
        }

        foreach ($form->getCustomFields() as $field) {
            try {
                array_push($entries, ...$this->collectFieldEntries($formHandle, $field));
            } catch (\Throwable $e) {
                $this->logInfo('Unable to collect Formie field translations', [
                    'form' => $formHandle,
                    'field' => $field->handle ?? null,
                    'class' => get_debug_type($field),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $entries;
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectFieldEntries(string $formHandle, $field): array
    {
        $entries = [];
        $fieldHandle = $field->handle;
        $context = "formie.{$formHandle}.{$fieldHandle}";

        $this->addEntry($entries, property_exists($field, 'label') ? $field->label : null, "{$context}.label");
        $this->addEntry($entries, property_exists($field, 'instructions') ? $field->instructions : null, "{$context}.instructions");
        $this->addEntry($entries, property_exists($field, 'placeholder') ? $field->placeholder : null, "{$context}.placeholder");
        $this->addEntry($entries, property_exists($field, 'errorMessage') ? $field->errorMessage : null, "{$context}.error");

        array_push($entries, ...$this->collectFieldTypeSpecificEntries($formHandle, $field));

        return $entries;
    }

    /**
     * @param array<int,array{text:string,context:string}> $entries
     */
    private function addEntry(array &$entries, mixed $text, string $context): void
    {
        if (!is_scalar($text)) {
            return;
        }

        $text = trim((string)$text);
        if ($text === '') {
            return;
        }

        $entries[] = [
            'text' => $text,
            'context' => $context,
        ];
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectFieldTypeSpecificEntries(string $formHandle, $field): array
    {
        $entries = [];
        $fieldClass = get_class($field);
        $fieldHandle = $field->handle;
        $context = "formie.{$formHandle}.{$fieldHandle}";
        // Formie's Date field can throw while building nested sub-fields when
        // fixed date limits are still strings during the form-save response.
        // The legacy property path below captures Date sub-field labels without
        // invoking Formie's Date sub-field builder.
        $nestedFieldEntries = $fieldClass === 'verbb\formie\fields\Date' ? [] : $this->collectNestedSubFieldEntries($formHandle, $field);
        $tableColumnEntries = $this->collectTableColumnEntries($formHandle, $field);

        $this->logDebug("Processing Formie field: {$fieldClass} ({$fieldHandle})");

        if ($nestedFieldEntries !== []) {
            array_push($entries, ...$nestedFieldEntries);
        }

        if ($tableColumnEntries !== []) {
            array_push($entries, ...$tableColumnEntries);
        }

        switch ($fieldClass) {
            case 'verbb\formie\fields\Dropdown':
            case 'verbb\formie\fields\Radio':
            case 'verbb\formie\fields\Checkboxes':
            case 'verbb\formie\fields\Categories':
            case 'verbb\formie\fields\Entries':
            case 'verbb\formie\fields\Products':
            case 'verbb\formie\fields\Tags':
            case 'verbb\formie\fields\Users':
            case 'verbb\formie\fields\Variants':
                $this->collectOptionEntries($entries, "{$context}.option", property_exists($field, 'options') ? $field->options : null);
                break;

            case 'verbb\formie\fields\Agree':
                $this->addEntry(
                    $entries,
                    method_exists($field, 'getDescriptionHtml') ? (string)$field->getDescriptionHtml() : null,
                    "{$context}.description"
                );
                $this->addEntry($entries, property_exists($field, 'checkedValue') ? $field->checkedValue : null, "{$context}.checkedValue");
                $this->addEntry($entries, property_exists($field, 'uncheckedValue') ? $field->uncheckedValue : null, "{$context}.uncheckedValue");
                break;

            case 'verbb\formie\fields\Address':
            case 'verbb\formie\fields\Name':
            case 'verbb\formie\fields\Date':
                if ($nestedFieldEntries !== []) {
                    break;
                }

                array_push($entries, ...$this->collectLegacyCompoundFieldEntries($formHandle, $field));
                break;

            case 'verbb\formie\fields\Html':
                $this->addEntry($entries, property_exists($field, 'htmlContent') ? $field->htmlContent : null, "{$context}.content");
                break;

            case 'verbb\formie\fields\Heading':
                $this->addEntry($entries, property_exists($field, 'headingText') ? $field->headingText : null, "{$context}.text");
                break;

            case 'verbb\formie\fields\Recipients':
                if (property_exists($field, 'sources') && is_array($field->sources)) {
                    foreach ($field->sources as $source) {
                        $this->addEntry($entries, is_array($source) ? ($source['label'] ?? null) : null, "{$context}.recipient");
                    }
                }
                break;

            case 'verbb\formie\fields\Table':
                $this->addEntry($entries, property_exists($field, 'addRowLabel') ? $field->addRowLabel : null, "{$context}.addRowLabel");
                break;

            case 'verbb\formie\fields\Repeater':
                $this->addEntry($entries, property_exists($field, 'addLabel') ? $field->addLabel : null, "{$context}.addLabel");
                $this->addEntry($entries, property_exists($field, 'removeLabel') ? $field->removeLabel : null, "{$context}.removeLabel");
                break;

            case 'lindemannrock\formieparagraphfield\fields\Paragraph':
                $this->addEntry($entries, property_exists($field, 'paragraphContent') ? $field->paragraphContent : null, "{$context}.content");
                break;

            case 'lindemannrock\formieratingfield\fields\Rating':
                if (property_exists($field, 'showEndpointLabels') && $field->showEndpointLabels) {
                    $this->addEntry($entries, property_exists($field, 'startLabel') ? $field->startLabel : null, "{$context}.startLabel");
                    $this->addEntry($entries, property_exists($field, 'endLabel') ? $field->endLabel : null, "{$context}.endLabel");
                }

                if (property_exists($field, 'customLabels') && is_array($field->customLabels)) {
                    foreach ($field->customLabels as $index => $labelData) {
                        if (!is_array($labelData)) {
                            continue;
                        }

                        $keyValue = !empty($labelData['value']) ? $labelData['value'] : $index;
                        $this->addEntry($entries, $labelData['label'] ?? null, "{$context}.customLabel.{$keyValue}");
                    }
                }

                if (property_exists($field, 'enableGoogleReview') && $field->enableGoogleReview) {
                    $this->addEntry($entries, $field->googleReviewMessageHigh ?: 'Thank you for the excellent rating! We would love if you could share your experience with others.', "{$context}.googleReview.messageHigh");
                    $this->addEntry($entries, $field->googleReviewMessageMedium ?: 'Thank you for your feedback!', "{$context}.googleReview.messageMedium");
                    $this->addEntry($entries, $field->googleReviewMessageLow ?: 'Thank you for your feedback. We will use it to improve our service.', "{$context}.googleReview.messageLow");
                    $this->addEntry($entries, $field->googleReviewButtonLabel ?: 'Review on Google', "{$context}.googleReview.buttonLabel");
                }
                break;

            case 'verbb\formie\fields\Section':
                $this->addEntry($entries, property_exists($field, 'sectionText') ? $field->sectionText : null, "{$context}.text");
                break;

            case 'verbb\formie\fields\Summary':
                $this->addEntry($entries, property_exists($field, 'summaryText') ? $field->summaryText : null, "{$context}.text");
                break;

            case 'verbb\formie\fields\FileUpload':
                $this->addEntry($entries, property_exists($field, 'uploadLocationText') ? $field->uploadLocationText : null, "{$context}.uploadText");
                if (property_exists($field, 'allowedKinds') && is_array($field->allowedKinds)) {
                    foreach ($field->allowedKinds as $kind) {
                        $this->addEntry($entries, $kind, "{$context}.allowedKind.{$kind}");
                    }
                }
                break;

            case 'verbb\formie\fields\Payment':
                $this->addEntry($entries, property_exists($field, 'currency') ? $field->currency : null, "{$context}.currency");
                $this->addEntry($entries, property_exists($field, 'paymentMethodLabel') ? $field->paymentMethodLabel : null, "{$context}.methodLabel");
                break;

            case 'verbb\formie\fields\Phone':
                $this->addEntry($entries, property_exists($field, 'countryLabel') ? $field->countryLabel : null, "{$context}.countryLabel");
                $this->addEntry($entries, property_exists($field, 'numberLabel') ? $field->numberLabel : null, "{$context}.numberLabel");
                break;

            case 'verbb\formie\fields\Password':
                $this->addEntry($entries, property_exists($field, 'confirmationLabel') ? $field->confirmationLabel : null, "{$context}.confirmationLabel");
                break;

            case 'verbb\formie\fields\Number':
                $this->addEntry($entries, property_exists($field, 'minLabel') ? $field->minLabel : null, "{$context}.minLabel");
                $this->addEntry($entries, property_exists($field, 'maxLabel') ? $field->maxLabel : null, "{$context}.maxLabel");
                $this->addEntry($entries, property_exists($field, 'unitText') ? $field->unitText : null, "{$context}.unitText");
                break;

            case 'verbb\formie\fields\Signature':
                $this->addEntry($entries, property_exists($field, 'clearLabel') ? $field->clearLabel : null, "{$context}.clearLabel");
                $this->addEntry($entries, property_exists($field, 'submitLabel') ? $field->submitLabel : null, "{$context}.submitLabel");
                break;

            case 'verbb\formie\fields\Calculations':
                $this->addEntry($entries, property_exists($field, 'calculationLabel') ? $field->calculationLabel : null, "{$context}.calculationLabel");
                break;

            case 'verbb\formie\fields\Group':
                if (method_exists($field, 'getCustomFields')) {
                    foreach ($field->getCustomFields() as $nestedField) {
                        array_push($entries, ...$this->collectFieldEntries($formHandle, $nestedField));
                    }
                }
                break;
        }

        return $entries;
    }

    /**
     * @param array<int,array{text:string,context:string}> $entries
     */
    private function collectOptionEntries(array &$entries, string $context, mixed $options): void
    {
        if (!is_array($options)) {
            return;
        }

        foreach ($options as $index => $option) {
            if (!is_array($option)) {
                continue;
            }

            $label = $option['label'] ?? null;
            $optionValue = $option['value'] ?? (is_scalar($label) ? (string)$label : (string)$index);
            $optionValue = $this->normalizeContextSegment((string)$optionValue);
            $this->addEntry($entries, $label, "{$context}.{$optionValue}");
        }
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectLegacyCompoundFieldEntries(string $formHandle, $field): array
    {
        $entries = [];
        $fieldHandle = $field->handle;

        $subfields = match (get_class($field)) {
            'verbb\formie\fields\Address' => [
                'address1' => $field->address1Enabled ?? false,
                'address2' => $field->address2Enabled ?? false,
                'address3' => $field->address3Enabled ?? false,
                'city' => $field->cityEnabled ?? false,
                'state' => $field->stateEnabled ?? false,
                'zip' => $field->zipEnabled ?? false,
                'country' => $field->countryEnabled ?? false,
            ],
            'verbb\formie\fields\Name' => [
                'prefix' => $field->prefixEnabled ?? false,
                'firstName' => true,
                'middleName' => $field->middleNameEnabled ?? false,
                'lastName' => $field->lastNameEnabled ?? false,
            ],
            'verbb\formie\fields\Date' => [
                'day' => $field->dayEnabled ?? false,
                'month' => $field->monthEnabled ?? false,
                'year' => $field->yearEnabled ?? false,
                'hour' => $field->hourEnabled ?? false,
                'minute' => $field->minuteEnabled ?? false,
                'second' => $field->secondEnabled ?? false,
                'ampm' => $field->ampmEnabled ?? false,
            ],
            default => [],
        };

        foreach ($subfields as $subfield => $enabled) {
            if (!$enabled) {
                continue;
            }

            $labelProp = $subfield . 'Label';
            $placeholderProp = $subfield . 'Placeholder';
            $contextPrefix = "formie.{$formHandle}.{$fieldHandle}.{$subfield}";

            $this->addEntry($entries, property_exists($field, $labelProp) ? $field->$labelProp : null, "{$contextPrefix}.label");
            $this->addEntry($entries, property_exists($field, $placeholderProp) ? $field->$placeholderProp : null, "{$contextPrefix}.placeholder");
        }

        return $entries;
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectTableColumnEntries(string $formHandle, $field): array
    {
        if (!property_exists($field, 'columns') || !is_array($field->columns)) {
            return [];
        }

        $entries = [];

        foreach ($field->columns as $col => $column) {
            if (is_object($column)) {
                $column = get_object_vars($column);
            }
            if (!is_array($column)) {
                continue;
            }

            $contextPrefix = "formie.{$formHandle}.{$field->handle}.column.{$col}";

            $this->addEntry($entries, $column['heading'] ?? null, $contextPrefix);
            $this->collectOptionEntries($entries, "{$contextPrefix}.option", $column['options'] ?? null);
        }

        return $entries;
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectNestedSubFieldEntries(string $formHandle, $field): array
    {
        $entries = [];

        foreach ($this->getNestedSubFields($field) as $subField) {
            $contextPrefix = "formie.{$formHandle}.{$field->handle}.{$subField->handle}";

            $this->addEntry($entries, $subField->label, "{$contextPrefix}.label");
            $this->addEntry($entries, $subField->instructions, "{$contextPrefix}.instructions");
            $this->addEntry($entries, property_exists($subField, 'placeholder') ? $subField->placeholder : null, "{$contextPrefix}.placeholder");
            $this->addEntry($entries, property_exists($subField, 'errorMessage') ? $subField->errorMessage : null, "{$contextPrefix}.error");
            $this->collectOptionEntries($entries, "{$contextPrefix}.option", property_exists($subField, 'options') ? $subField->options : null);
        }

        return $entries;
    }

    /**
     * Return enabled nested Formie sub-fields for compound fields.
     *
     * @return array<int,mixed>
     */
    private function getNestedSubFields($field): array
    {
        if (!method_exists($field, 'getFields')) {
            return [];
        }

        $subFields = [];

        try {
            $fields = $field->getFields();
        } catch (\Throwable $e) {
            $this->logInfo('Unable to inspect Formie nested sub-fields', [
                'field' => $field->handle ?? null,
                'class' => get_debug_type($field),
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        foreach ($fields as $subField) {
            if (method_exists($subField, 'getIsDisabled') && $subField->getIsDisabled()) {
                continue;
            }

            $subFields[] = $subField;
        }

        return $subFields;
    }

    /**
     * Capture default Formie translations (validation messages, error strings, etc.)
     *
     * @return array Captured translations
     */
    private function captureDefaultTranslations(): array
    {
        $captured = [];

        try {
            // Get the Rendering service to access default translation strings
            $renderingService = \verbb\formie\Formie::getInstance()->getRendering();

            if (!method_exists($renderingService, 'getFrontEndJsTranslations')) {
                return [];
            }

            // Get the default translation strings
            // Returns array like: ['{attribute} cannot be blank.' => 'Translated text']
            $defaultStrings = $renderingService->getFrontEndJsTranslations();
            $usedDefaultKeys = [];

            // Create translations for each default string
            foreach ($defaultStrings as $originalText => $translatedText) {
                if (!empty($originalText)) {
                    // Always use the original English text as the source
                    $captured[] = $this->createTranslation(
                        $originalText,
                        'formie.defaults.' . $this->buildDefaultContextKey($originalText, $usedDefaultKeys)
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logInfo("Unable to capture default Formie translations", ['error' => $e->getMessage()]);
        }

        return array_filter($captured);
    }

    /**
     * Generate a clean key from a translation string
     *
     * @param string $string The translation string
     * @return string A clean key suitable for use in translation keys
     */
    private function generateCleanKey(string $string): string
    {
        // Remove placeholder patterns like {attribute}, {min}, {max}, etc.
        $clean = preg_replace('/\{[^}]+\}/', '', $string);

        // Remove special characters and extra spaces
        $clean = preg_replace('/[^a-zA-Z0-9\s]/', '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim($clean);

        // Convert to kebab case
        $clean = strtolower($clean);
        $clean = str_replace(' ', '-', $clean);

        // If the result is empty, use a hash of the original string
        if (empty($clean)) {
            $clean = 'msg-' . substr(md5($string), 0, 8);
        }

        return $clean;
    }

    /**
     * Build the page button context while preserving the legacy single-page key.
     */
    private function buildPageButtonContext(string $formHandle, int $pageIndex, bool $hasMultiplePages): string
    {
        return $hasMultiplePages
            ? "formie.{$formHandle}.page.{$pageIndex}.button"
            : "formie.{$formHandle}.button";
    }

    /**
     * Build a unique default translation context key for a capture batch.
     *
     * @param array<string,true> $usedKeys
     */
    private function buildDefaultContextKey(string $text, array &$usedKeys): string
    {
        $cleanKey = $this->generateCleanKey($text);
        if (isset($usedKeys[$cleanKey])) {
            $cleanKey .= '-' . substr(md5($text), 0, 8);
        }

        $usedKeys[$cleanKey] = true;

        return $cleanKey;
    }

    /**
     * Convert TipTap JSON to clean HTML
     */
    private function convertTipTapToHtml($content): string
    {
        if (empty($content)) {
            return '';
        }

        // If content is already an array (JSON-decoded), use it directly
        if (is_array($content)) {
            $data = $content;
        }
        // If it's a string, try to decode it
        elseif (is_string($content)) {
            // If it doesn't look like JSON, return as-is
            if ($content[0] !== '[' && $content[0] !== '{') {
                return $content;
            }

            try {
                $data = json_decode($content, true);
                if (!is_array($data)) {
                    return $content;
                }
            } catch (\Exception) {
                // If JSON parsing fails, return original
                return $content;
            }
        }
        // If it's neither string nor array, just convert to string
        else {
            return (string)$content;
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
    }
}
