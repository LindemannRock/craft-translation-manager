<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\integrations\FormieIntegration;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.30.0
 */
final class FormieIntegrationTableColumnTest extends TestCase
{
    public function testTableSelectOptionLabelsAreCaptured(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'collectTableColumnEntries');
        $field = new FormieTableColumnFieldStub('schedule', [
            'slot' => [
                'heading' => self::MARKER . 'formie_table_slot_heading',
                'options' => [
                    [
                        'label' => self::MARKER . 'formie_table_morning_option',
                        'value' => 'morning',
                    ],
                ],
            ],
        ]);

        $entries = $method->invoke($integration, 'contact', $field);

        self::assertContains([
            'text' => self::MARKER . 'formie_table_slot_heading',
            'context' => 'formie.contact.schedule.column.slot',
        ], $entries);
        self::assertContains([
            'text' => self::MARKER . 'formie_table_morning_option',
            'context' => 'formie.contact.schedule.column.slot.option.morning',
        ], $entries);
    }

    public function testTableSelectOptionLabelsAreIncludedInSharedFieldEntries(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'collectFieldEntries');
        $field = new FormieTableColumnFieldStub('schedule', [
            'slot' => [
                'heading' => self::MARKER . 'formie_table_active_heading',
                'options' => [
                    [
                        'label' => self::MARKER . 'formie_table_active_option',
                        'value' => 'morning',
                    ],
                ],
            ],
        ]);

        $entries = $method->invoke($integration, 'contact', $field);
        $activeTexts = array_fill_keys(array_column($entries, 'text'), true);

        self::assertArrayHasKey(self::MARKER . 'formie_table_active_heading', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_table_active_option', $activeTexts);
    }
}

final class FormieTableColumnFieldStub
{
    /**
     * @param array<string,array<string,mixed>> $columns
     */
    public function __construct(
        public string $handle,
        public array $columns,
    ) {
    }
}
