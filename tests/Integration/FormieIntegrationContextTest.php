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
 * @since 5.33.0
 */
final class FormieIntegrationContextTest extends TestCase
{
    public function testMultiPageButtonContextsIncludePageIndex(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'buildPageButtonContext');

        self::assertSame(
            'formie.contact.page.1.button',
            $method->invoke($integration, 'contact', 1, true),
        );
    }

    public function testSinglePageButtonContextsKeepLegacyShape(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'buildPageButtonContext');

        self::assertSame(
            'formie.contact.button',
            $method->invoke($integration, 'contact', 0, false),
        );
    }

    public function testDefaultContextKeysGetHashSuffixOnCollision(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'buildDefaultContextKey');
        $usedKeys = [];

        $first = $method->invokeArgs($integration, [
            'File {filename} must be smaller than {filesize} MB.',
            &$usedKeys,
        ]);
        $second = $method->invokeArgs($integration, [
            'File must be smaller than {filesize} MB.',
            &$usedKeys,
        ]);

        self::assertSame('file-must-be-smaller-than-mb', $first);
        self::assertSame('file-must-be-smaller-than-mb-' . substr(md5('File must be smaller than {filesize} MB.'), 0, 8), $second);
    }

    public function testOptionContextSegmentsAreNormalized(): void
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'normalizeContextSegment');

        self::assertSame('another-one', $method->invoke($integration, 'Another One'));
        self::assertSame('anothr-test', $method->invoke($integration, 'Anothr Test'));
    }
}
