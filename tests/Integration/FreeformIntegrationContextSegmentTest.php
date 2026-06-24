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
 * @since 5.26.0
 */
final class FreeformIntegrationContextSegmentTest extends TestCase
{
    public function testNormalizeContextSegmentConvertsValuesToLowerKebabCase(): void
    {
        $integration = new FreeformIntegration();
        $method = new \ReflectionMethod($integration, 'normalizeContextSegment');

        self::assertSame('another-one', $method->invoke($integration, 'Another-One'));
        self::assertSame('support-request', $method->invoke($integration, 'Support Request'));
    }

    public function testNormalizeContextSegmentHashesOriginalValueWhenSanitizedValueIsEmpty(): void
    {
        $integration = new FreeformIntegration();
        $method = new \ReflectionMethod($integration, 'normalizeContextSegment');

        self::assertSame(substr(md5('***'), 0, 8), $method->invoke($integration, '***'));
        self::assertSame(substr(md5('###'), 0, 8), $method->invoke($integration, '###'));
        self::assertNotSame($method->invoke($integration, '***'), $method->invoke($integration, '###'));
    }
}
