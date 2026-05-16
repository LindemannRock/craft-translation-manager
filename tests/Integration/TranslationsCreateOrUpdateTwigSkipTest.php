<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\tests\TestCase;

/**
 * Pins the Twig-pollution guard in `createOrUpdateTranslation()`: a string
 * containing bare Twig syntax (`{{ … }}`, `{% … %}`, `{# … #}`) and no
 * extractable `x:component` plain-text body must NOT be persisted.
 *
 * Without this guard, runtime captures and template scans would persist
 * Twig placeholder fragments as translation keys — keys that can never be
 * matched against rendered output, so they accumulate as permanent dead rows.
 *
 * @since 5.24.0
 */
final class TranslationsCreateOrUpdateTwigSkipTest extends TestCase
{
    public function testBareTwigVariableProducesNoRows(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $source = self::MARKER . 'twig_skip_' . bin2hex(random_bytes(4)) . ' {{ name }}';

        $result = $this->translations->createOrUpdateTranslation($source, 'site');

        self::assertNull(
            $result,
            'createOrUpdateTranslation() should return null for bare Twig text.',
        );

        $rows = $this->fetchRowsForSource($source);
        self::assertCount(
            0,
            $rows,
            'No rows should be persisted for source strings containing bare Twig syntax.',
        );
    }
}
