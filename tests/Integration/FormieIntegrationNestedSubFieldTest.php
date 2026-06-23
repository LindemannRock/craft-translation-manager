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
        $this->requireLatinSourceLanguage();

        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'captureNestedSubFieldTranslations');
        $form = new FormieNestedSubFieldFormStub('contact');
        $field = new FormieNestedSubFieldParentStub('name', [
            new FormieNestedSubFieldStub(
                handle: 'firstName',
                label: self::MARKER . 'formie_subfield_first_label',
                instructions: self::MARKER . 'formie_subfield_first_instructions',
                placeholder: self::MARKER . 'formie_subfield_first_placeholder',
                errorMessage: self::MARKER . 'formie_subfield_first_error',
            ),
        ]);

        $method->invoke($integration, $form, $field);

        self::assertSame(
            'formie.contact.name.firstName.label',
            $this->fetchRowsForSource(self::MARKER . 'formie_subfield_first_label')[0]['context'] ?? null,
        );
        self::assertSame(
            'formie.contact.name.firstName.instructions',
            $this->fetchRowsForSource(self::MARKER . 'formie_subfield_first_instructions')[0]['context'] ?? null,
        );
        self::assertSame(
            'formie.contact.name.firstName.placeholder',
            $this->fetchRowsForSource(self::MARKER . 'formie_subfield_first_placeholder')[0]['context'] ?? null,
        );
        self::assertSame(
            'formie.contact.name.firstName.error',
            $this->fetchRowsForSource(self::MARKER . 'formie_subfield_first_error')[0]['context'] ?? null,
        );
    }

    public function testNestedSubFieldStringsAreCollectedAsActiveTexts(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'collectFieldTexts');
        $field = new FormieNestedSubFieldParentStub('name', [
            new FormieNestedSubFieldStub(
                handle: 'firstName',
                label: self::MARKER . 'formie_subfield_active_label',
                instructions: self::MARKER . 'formie_subfield_active_instructions',
                placeholder: self::MARKER . 'formie_subfield_active_placeholder',
                errorMessage: self::MARKER . 'formie_subfield_active_error',
            ),
        ]);
        $activeTexts = [];

        $method->invokeArgs($integration, [$field, &$activeTexts]);

        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_label', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_instructions', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_placeholder', $activeTexts);
        self::assertArrayHasKey(self::MARKER . 'formie_subfield_active_error', $activeTexts);
    }
}

final class FormieNestedSubFieldFormStub
{
    public function __construct(public string $handle)
    {
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
