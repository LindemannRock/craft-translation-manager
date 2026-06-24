<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\integrations\FreeformIntegration;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.30.0
 */
final class FreeformIntegrationFieldEntryTest extends TestCase
{
    public function testFieldRawPropertiesAreCaptured(): void
    {
        $entries = $this->collectFieldEntries(new FreeformFieldEntryStub(
            handle: 'summary',
            label: self::MARKER . 'freeform_field_label',
            instructions: self::MARKER . 'freeform_field_instructions',
            placeholder: self::MARKER . 'freeform_field_placeholder',
            content: self::MARKER . 'freeform_field_content',
            message: self::MARKER . 'freeform_field_message',
            addButtonLabel: self::MARKER . 'freeform_add_button_label',
            addButtonMarkup: self::MARKER . 'freeform_add_button_markup',
            removeButtonLabel: self::MARKER . 'freeform_remove_button_label',
            removeButtonMarkup: self::MARKER . 'freeform_remove_button_markup',
        ));

        self::assertContains(['text' => self::MARKER . 'freeform_field_label', 'context' => 'freeform.contact.summary.label'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_field_instructions', 'context' => 'freeform.contact.summary.instructions'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_field_placeholder', 'context' => 'freeform.contact.summary.placeholder'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_field_content', 'context' => 'freeform.contact.summary.content'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_field_message', 'context' => 'freeform.contact.summary.message'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_add_button_label', 'context' => 'freeform.contact.summary.addButtonLabel'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_add_button_markup', 'context' => 'freeform.contact.summary.addButtonMarkup'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_remove_button_label', 'context' => 'freeform.contact.summary.removeButtonLabel'], $entries);
        self::assertContains(['text' => self::MARKER . 'freeform_remove_button_markup', 'context' => 'freeform.contact.summary.removeButtonMarkup'], $entries);
    }

    public function testOptionLabelsAreCaptured(): void
    {
        $entries = $this->collectFieldEntries(new FreeformFieldEntryStub(
            handle: 'topic',
            optionConfiguration: new FreeformArrayConfigurationStub([
                'emptyOption' => self::MARKER . 'freeform_empty_option',
                'options' => [
                    [
                        'label' => self::MARKER . 'freeform_support_option',
                        'value' => 'support request',
                    ],
                ],
            ]),
        ));

        self::assertContains([
            'text' => self::MARKER . 'freeform_empty_option',
            'context' => 'freeform.contact.topic.option.empty',
        ], $entries);
        self::assertContains([
            'text' => self::MARKER . 'freeform_support_option',
            'context' => 'freeform.contact.topic.option.support-request',
        ], $entries);
    }

    public function testTableColumnAndOptionLabelsAreCaptured(): void
    {
        $entries = $this->collectFieldEntries(new FreeformFieldEntryStub(
            handle: 'availability',
            tableLayout: new FreeformArrayConfigurationStub([
                'columns' => [
                    [
                        'uid' => 'time slot',
                        'label' => self::MARKER . 'freeform_table_time_label',
                        'options' => [
                            [
                                'label' => self::MARKER . 'freeform_table_morning_option',
                                'value' => 'morning',
                            ],
                        ],
                    ],
                ],
            ]),
        ));

        self::assertContains([
            'text' => self::MARKER . 'freeform_table_time_label',
            'context' => 'freeform.contact.availability.column.time-slot.label',
        ], $entries);
        self::assertContains([
            'text' => self::MARKER . 'freeform_table_morning_option',
            'context' => 'freeform.contact.availability.column.time-slot.option.morning',
        ], $entries);
    }

    /**
     * @return array<int,array{text:string,context:string}>
     */
    private function collectFieldEntries(object $field): array
    {
        $integration = new FreeformIntegration();
        $method = new \ReflectionMethod($integration, 'collectFieldEntries');
        $entries = [];

        $method->invokeArgs($integration, [&$entries, 'contact', $field]);

        return $entries;
    }
}

final class FreeformFieldEntryStub
{
    public function __construct(
        private string $handle,
        private string $uid = 'field-uid',
        private bool $required = false,
        private string $requiredMessage = '',
        private string $label = '',
        private string $instructions = '',
        private string $placeholder = '',
        private string $content = '',
        private string $message = '',
        private string $addButtonLabel = '',
        private string $addButtonMarkup = '',
        private string $removeButtonLabel = '',
        private string $removeButtonMarkup = '',
        private ?FreeformArrayConfigurationStub $optionConfiguration = null,
        private ?FreeformArrayConfigurationStub $tableLayout = null,
    ) {
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}

final class FreeformArrayConfigurationStub
{
    /**
     * @param array<string,mixed> $configuration
     */
    public function __construct(private array $configuration)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->configuration;
    }
}
