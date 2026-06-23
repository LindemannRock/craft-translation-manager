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
        $this->requireLatinSourceLanguage();

        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'captureTableColumnTranslations');
        $form = new FormieTableColumnFormStub('contact');
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

        $method->invoke($integration, $form, $field);

        self::assertSame(
            'formie.contact.schedule.column.slot',
            $this->fetchRowsForSource(self::MARKER . 'formie_table_slot_heading')[0]['context'] ?? null,
        );
        self::assertSame(
            'formie.contact.schedule.column.slot.option.morning',
            $this->fetchRowsForSource(self::MARKER . 'formie_table_morning_option')[0]['context'] ?? null,
        );
    }

    public function testTableSelectOptionLabelsAreCollectedAsActiveTexts(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'collectTableColumnTexts');
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
        $activeTexts = [];

        $method->invokeArgs($integration, [$field, &$activeTexts]);

        self::assertArrayHasKey(self::MARKER . 'formie_table_active_heading', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_table_active_option', $activeTexts);
    }
}

final class FormieTableColumnFormStub
{
    public function __construct(public string $handle)
    {
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
