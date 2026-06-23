<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use Craft;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins utility/statistics scoping to the canonical language model.
 *
 * @since 5.30.0
 */
final class TranslationStatisticsLanguageScopeTest extends TestCase
{
    public function testLanguageScopeTakesPrecedenceOverLegacySiteId(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $settings = TranslationManager::getInstance()->getSettings();
        $language = $settings->mapLanguage(Craft::$app->getSites()->getCurrentSite()->language);
        $source = self::MARKER . 'stats_language_scope_' . bin2hex(random_bytes(4));

        $before = $this->translations->getStatistics(null, $language);

        $this->translations->createOrUpdateTranslation($source, 'site');

        $after = $this->translations->getStatistics(999999, $language);

        self::assertSame($before['total'] + 1, $after['total']);
        self::assertSame($before['site'] + 1, $after['site']);
    }
}
