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
final class FreeformIntegrationRequiredMessageTest extends TestCase
{
    public function testRequiredFieldWithoutCustomMessageCapturesFreeformDefault(): void
    {
        $entries = $this->collectFieldEntries(new RequiredMessageFieldStub(
            handle: 'email',
            required: true,
            requiredMessage: '',
        ));

        self::assertContains(
            [
                'text' => 'This field is required',
                'context' => 'freeform.contact.email.required',
            ],
            $entries,
        );
    }

    public function testCustomRequiredMessageTakesPrecedence(): void
    {
        $entries = $this->collectFieldEntries(new RequiredMessageFieldStub(
            handle: 'email',
            required: true,
            requiredMessage: 'Please enter your email address.',
        ));

        self::assertContains(
            [
                'text' => 'Please enter your email address.',
                'context' => 'freeform.contact.email.required',
            ],
            $entries,
        );
        self::assertNotContains(
            [
                'text' => 'This field is required',
                'context' => 'freeform.contact.email.required',
            ],
            $entries,
        );
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

final class RequiredMessageFieldStub
{
    public function __construct(
        private string $handle,
        private bool $required,
        private string $requiredMessage,
    ) {
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getUid(): string
    {
        return 'field-uid';
    }

    public function isRequired(): bool
    {
        return $this->required;
    }
}
