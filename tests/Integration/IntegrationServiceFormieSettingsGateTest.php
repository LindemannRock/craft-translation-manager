<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.26.0
 */
final class IntegrationServiceFormieSettingsGateTest extends TestCase
{
    public function testEagerFormieHookRegistrationChecksIntegrationSetting(): void
    {
        $reflection = new \ReflectionClass(IntegrationService::class);
        $method = $reflection->getMethod('registerEventHandlers');
        $source = file((string)$reflection->getFileName());

        self::assertIsArray($source);

        $body = implode('', array_slice(
            $source,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        self::assertStringContainsString("isIntegrationEnabled('formie')", $body);
        self::assertStringContainsString("isIntegrationEnabled('freeform')", $body);
    }
}
