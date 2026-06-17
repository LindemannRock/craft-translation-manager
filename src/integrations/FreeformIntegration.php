<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Freeform plugin integration
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\integrations;

use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\freeform\TmTranslationsService;
use Solspace\Freeform\Bundles\Translations\TranslationProvider;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Services\Form\LayoutsService;
use Solspace\Freeform\Services\Form\TranslationsService as FreeformTranslationsService;
use Solspace\Freeform\Services\FormsService;
use yii\base\Event;

/**
 * Freeform Integration
 *
 * Handles translation capture and management for Freeform forms.
 *
 * @since 5.26.0
 */
class FreeformIntegration extends BaseIntegration
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'freeform';
    }

    /**
     * @inheritdoc
     */
    public function getPluginHandle(): string
    {
        return 'freeform';
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(): bool
    {
        return PluginHelper::isPluginEnabled('freeform')
            && class_exists(\Solspace\Freeform\Freeform::class);
    }

    /**
     * @inheritdoc
     */
    public function registerHooks(): void
    {
        if (!$this->isAvailable()) {
            $this->logInfo('FreeformIntegration: Not available - Freeform plugin not found or not enabled');
            return;
        }

        $this->registerTranslationFallbackService();
        $this->registerFormAttributeFallback();

        Event::on(
            \Solspace\Freeform\controllers\api\FormsController::class,
            \Solspace\Freeform\controllers\api\FormsController::EVENT_AFTER_SAVE_FORM,
            function(\Solspace\Freeform\Events\Forms\PersistFormEvent $event) {
                $form = $event->getForm();
                if (!$form instanceof Form) {
                    return;
                }

                $this->logInfo('FreeformIntegration: Form saved', ['handle' => $form->getHandle()]);
                $this->handleFormSave($form);
            }
        );

        Event::on(
            \Solspace\Freeform\Services\FormsService::class,
            \Solspace\Freeform\Services\FormsService::EVENT_AFTER_DELETE,
            function(\Solspace\Freeform\Events\Forms\DeleteEvent $event) {
                $form = $event->getForm();

                $this->logInfo('FreeformIntegration: Form deleted', ['handle' => $form->getHandle()]);
                $this->handleFormDelete($form);
            }
        );
    }

    private function registerFormAttributeFallback(): void
    {
        Event::on(
            Form::class,
            Form::EVENT_ATTACH_TAG_ATTRIBUTES,
            static function(\Solspace\Freeform\Events\Forms\AttachFormAttributesEvent $event): void {
                $form = $event->getForm();
                $attributes = $form->getAttributes();
                $behaviorSettings = $form->getSettings()->getBehavior();

                if ($behaviorSettings->showProcessingText) {
                    $attributes->replace('data-processing-text', $form->getProcessingText());
                }

                $attributes->replace('data-success-message', $form->getSuccessMessage());
                $attributes->replace('data-error-message', $form->getErrorMessage());
            }
        );
    }

    private function registerTranslationFallbackService(): void
    {
        \Craft::$container->setSingleton(FreeformTranslationsService::class, TmTranslationsService::class);
        \Craft::$container->setSingleton(TranslationProvider::class, static function(): TranslationProvider {
            return new TranslationProvider(\Craft::$container->get(FreeformTranslationsService::class));
        });
        \Craft::$container->setSingleton(FormsService::class, FormsService::class);
        \Craft::$container->setSingleton(LayoutsService::class, LayoutsService::class);

        $freeform = \Solspace\Freeform\Freeform::getInstance();
        $freeform->set('forms', FormsService::class);
        $freeform->set('formLayouts', LayoutsService::class);

        if ($freeform->get('translations') instanceof TmTranslationsService) {
            return;
        }

        $freeform->set('translations', TmTranslationsService::class);
    }

    /**
     * @inheritdoc
     */
    public function captureTranslations($element): array
    {
        if (!$element instanceof Form) {
            return [];
        }

        if ($this->isFormExcluded($element->getHandle(), $element->getName())) {
            $this->logInfo('Skipping excluded Freeform form', [
                'handle' => $element->getHandle(),
                'name' => $element->getName(),
            ]);
            return [];
        }

        $captured = [];
        foreach ($this->collectTranslationEntries($element) as $entry) {
            $captured[] = $this->createTranslation($entry['text'], $entry['context']);
        }

        return array_filter($captured);
    }

    /**
     * @inheritdoc
     */
    public function checkUsage(): void
    {
        $result = $this->getTranslationsService()->recheckUsage();
        $this->logInfo('Checked Freeform translation usage', $result);
    }

    /**
     * @inheritdoc
     */
    public function getActiveTranslationTexts(): array
    {
        $activeTexts = [];

        foreach ($this->getAllForms() as $form) {
            if ($this->isFormExcluded($form->getHandle(), $form->getName())) {
                continue;
            }

            foreach ($this->collectTranslationEntries($form) as $entry) {
                $activeTexts[$entry['text']] = true;
            }
        }

        $this->logDebug('Collected Freeform active texts', ['count' => count($activeTexts)]);

        return $activeTexts;
    }

    /**
     * @inheritdoc
     */
    public function recaptureAll(): array
    {
        $processed = 0;
        $captured = 0;

        if (!$this->isAvailable()) {
            return ['processed' => 0, 'captured' => 0];
        }

        foreach ($this->getAllForms() as $form) {
            $captured += count($this->captureTranslations($form));
            $processed++;
        }

        $this->checkUsage();

        return ['processed' => $processed, 'captured' => $captured];
    }

    /**
     * @inheritdoc
     */
    public function getSupportedContentTypes(): array
    {
        return [
            'forms' => 'Freeform Forms',
            'fields' => 'Freeform Fields',
            'options' => 'Field Options',
            'buttons' => 'Form Buttons',
            'messages' => 'Form Messages',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getTranslationType(): string
    {
        return 'forms';
    }

    private function handleFormSave(Form $form): void
    {
        $this->logInfo('Processing Freeform form save', ['handle' => $form->getHandle()]);

        $captured = $this->captureTranslations($form);

        $this->logInfo('Captured translations from Freeform form', [
            'handle' => $form->getHandle(),
            'count' => count($captured),
        ]);

        $this->checkUsage();
    }

    private function handleFormDelete(Form $form): void
    {
        $handle = $form->getHandle();

        $translations = $this->getTranslationsService()->getTranslations([
            'type' => 'forms',
            'category' => $this->getCategory(),
            'search' => "{$this->getContextPrefix()}.{$handle}.",
            'allSites' => true,
        ]);

        $translationIds = array_column($translations, 'id');
        $marked = $this->markTranslationsUnused($translationIds);

        $this->logInfo('Marked Freeform translations as unused after form deletion', ['marked' => $marked]);
    }

    /**
     * @return Form[]
     */
    private function getAllForms(): array
    {
        return \Solspace\Freeform\Freeform::getInstance()->forms->getAllForms();
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectTranslationEntries(Form $form): array
    {
        $entries = [];
        $formHandle = $form->getHandle();

        $general = $form->getSettings()->getGeneral();
        $this->addEntry($entries, $general->name ?? '', "freeform.{$formHandle}.title");
        $this->addEntry($entries, $general->description ?? '', "freeform.{$formHandle}.description");

        $behavior = $form->getSettings()->getBehavior();
        $this->addEntry($entries, $behavior->successMessage ?? '', "freeform.{$formHandle}.message.success");
        $this->addEntry($entries, $behavior->errorMessage ?? '', "freeform.{$formHandle}.message.error");
        $this->addEntry($entries, $behavior->processingText ?? '', "freeform.{$formHandle}.message.processing");

        foreach ($form->getLayout()->getPages() as $page) {
            $pageUid = $this->contextSegment($page->getUid() ?: 'page-' . $page->getIndex());
            $this->addEntry($entries, $this->readRawProperty($page, 'label'), "freeform.{$formHandle}.page.{$pageUid}.label");

            $buttons = $page->getButtons();
            $this->addEntry($entries, $this->readRawProperty($buttons, 'submitLabel'), "freeform.{$formHandle}.page.{$pageUid}.button.submit");

            if ($this->readRawProperty($buttons, 'back')) {
                $this->addEntry($entries, $this->readRawProperty($buttons, 'backLabel'), "freeform.{$formHandle}.page.{$pageUid}.button.back");
            }

            if ($this->readRawProperty($buttons, 'save')) {
                $this->addEntry($entries, $this->readRawProperty($buttons, 'saveLabel'), "freeform.{$formHandle}.page.{$pageUid}.button.save");
            }
        }

        foreach ($form->getLayout()->getFields() as $field) {
            $this->collectFieldEntries($entries, $formHandle, $field);
        }

        return $entries;
    }

    /**
     * @param array<int,array{text:string,context:string}> $entries
     */
    private function collectFieldEntries(array &$entries, string $formHandle, object $field): void
    {
        $fieldHandle = $this->contextSegment((string) ($field->getHandle() ?: $field->getUid() ?: 'field'));
        $context = "freeform.{$formHandle}.{$fieldHandle}";

        $this->addEntry($entries, $this->readRawProperty($field, 'label'), "{$context}.label");
        $this->addEntry($entries, $this->readRawProperty($field, 'instructions'), "{$context}.instructions");
        $this->addEntry($entries, $this->readRawProperty($field, 'requiredMessage'), "{$context}.required");

        foreach (['placeholder', 'content', 'message', 'addButtonLabel', 'addButtonMarkup', 'removeButtonLabel', 'removeButtonMarkup'] as $property) {
            $this->addEntry($entries, $this->readRawProperty($field, $property), "{$context}.{$property}");
        }

        $this->collectOptionEntries($entries, $context, $field);
        $this->collectTableEntries($entries, $context, $field);
    }

    /**
     * @param array<int,array{text:string,context:string}> $entries
     */
    private function collectOptionEntries(array &$entries, string $context, object $field): void
    {
        $optionConfiguration = $this->readRawProperty($field, 'optionConfiguration');
        if (!is_object($optionConfiguration) || !method_exists($optionConfiguration, 'toArray')) {
            return;
        }

        $configuration = $optionConfiguration->toArray();
        if (!is_array($configuration)) {
            return;
        }

        $this->addEntry($entries, $configuration['emptyOption'] ?? null, "{$context}.option.empty");

        $options = $configuration['options'] ?? null;
        if (!is_array($options)) {
            return;
        }

        foreach ($options as $index => $option) {
            if (!is_array($option)) {
                continue;
            }

            $key = $this->contextSegment((string)($option['value'] ?? $index));
            $this->addEntry($entries, $option['label'] ?? null, "{$context}.option.{$key}");
        }
    }

    /**
     * @param array<int,array{text:string,context:string}> $entries
     */
    private function collectTableEntries(array &$entries, string $context, object $field): void
    {
        $tableLayout = $this->readRawProperty($field, 'tableLayout');
        if (!is_object($tableLayout) || !method_exists($tableLayout, 'toArray')) {
            return;
        }

        $layout = $tableLayout->toArray();
        if (!is_array($layout)) {
            return;
        }

        $columns = $layout['columns'] ?? $layout;
        if (!is_array($columns)) {
            return;
        }

        foreach ($columns as $index => $column) {
            if (is_object($column)) {
                $column = get_object_vars($column);
            }
            if (!is_array($column)) {
                continue;
            }

            $key = $this->contextSegment((string)($column['uid'] ?? $column['handle'] ?? $index));
            $this->addEntry($entries, $column['label'] ?? $column['heading'] ?? null, "{$context}.column.{$key}.label");

            if (!empty($column['options']) && is_array($column['options'])) {
                foreach ($column['options'] as $optionIndex => $option) {
                    if (is_object($option)) {
                        $option = get_object_vars($option);
                    }
                    if (!is_array($option)) {
                        continue;
                    }

                    $optionKey = $this->contextSegment((string)($option['value'] ?? $optionIndex));
                    $this->addEntry($entries, $option['label'] ?? null, "{$context}.column.{$key}.option.{$optionKey}");
                }
            }
        }
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

    private function readRawProperty(object $object, string $property): mixed
    {
        $class = new \ReflectionClass($object);

        do {
            if ($class->hasProperty($property)) {
                $reflectionProperty = $class->getProperty($property);
                $reflectionProperty->setAccessible(true);

                return $reflectionProperty->getValue($object);
            }

            $class = $class->getParentClass();
        } while ($class instanceof \ReflectionClass);

        return null;
    }

    private function contextSegment(string $value): string
    {
        $originalValue = $value;
        $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value) ?: '';
        $value = trim($value, '-_');

        return $value !== '' ? $value : substr(md5($originalValue), 0, 8);
    }
}
