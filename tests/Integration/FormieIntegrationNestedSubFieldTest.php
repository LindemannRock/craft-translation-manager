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
final class FormieIntegrationNestedSubFieldTest extends TestCase
{
    public function testNestedSubFieldStringsAreCapturedWithStableCompoundContexts(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'collectNestedSubFieldEntries');
        $field = new FormieNestedSubFieldParentStub('name', [
            new FormieNestedSubFieldStub(
                handle: 'firstName',
                label: self::MARKER . 'formie_subfield_first_label',
                instructions: self::MARKER . 'formie_subfield_first_instructions',
                placeholder: self::MARKER . 'formie_subfield_first_placeholder',
                errorMessage: self::MARKER . 'formie_subfield_first_error',
            ),
        ]);

        $entries = $method->invoke($integration, 'contact', $field);

        self::assertContains([
            'text' => self::MARKER . 'formie_subfield_first_label',
            'context' => 'formie.contact.name.firstName.label',
        ], $entries);
        self::assertContains([
            'text' => self::MARKER . 'formie_subfield_first_instructions',
            'context' => 'formie.contact.name.firstName.instructions',
        ], $entries);
        self::assertContains([
            'text' => self::MARKER . 'formie_subfield_first_placeholder',
            'context' => 'formie.contact.name.firstName.placeholder',
        ], $entries);
        self::assertContains([
            'text' => self::MARKER . 'formie_subfield_first_error',
            'context' => 'formie.contact.name.firstName.error',
        ], $entries);
    }

    public function testNestedSubFieldStringsAreIncludedInSharedFieldEntries(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'collectFieldEntries');
        $field = new FormieNestedSubFieldParentStub('name', [
            new FormieNestedSubFieldStub(
                handle: 'firstName',
                label: self::MARKER . 'formie_subfield_active_label',
                instructions: self::MARKER . 'formie_subfield_active_instructions',
                placeholder: self::MARKER . 'formie_subfield_active_placeholder',
                errorMessage: self::MARKER . 'formie_subfield_active_error',
            ),
        ]);

        $entries = $method->invoke($integration, 'contact', $field);
        $activeTexts = array_fill_keys(array_column($entries, 'text'), true);

        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_label', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_instructions', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_placeholder', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_error', $activeTexts);
    }
}

final class FormieNestedSubFieldParentStub
{
    public string $label = '';
    public string $instructions = '';

    /**
     * @param array<int,FormieNestedSubFieldStub> $fields
     */
    public function __construct(
        public string $handle,
        private array $fields,
    ) {
    }

    /**
     * @return array<int,FormieNestedSubFieldStub>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}

final class FormieNestedSubFieldStub
{
    /**
     * @param array<int,array{label:string,value:string}> $options
     */
    public function __construct(
        public string $handle,
        public string $label = '',
        public string $instructions = '',
        public string $placeholder = '',
        public string $errorMessage = '',
        public array $options = [],
        private bool $disabled = false,
    ) {
    }

    public function getIsDisabled(): bool
    {
        return $this->disabled;
    }
}
