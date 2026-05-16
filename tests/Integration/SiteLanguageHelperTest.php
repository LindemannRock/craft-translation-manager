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
use lindemannrock\translationmanager\helpers\SiteLanguageHelper;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * Covers the shared language-to-site lookup used by import, PHP import,
 * missing-translation capture, and backup restore paths.
 *
 * @since 5.24.0
 */
final class SiteLanguageHelperTest extends TestCase
{
    public function testExactLanguageMatchReturnsFirstMatchingSite(): void
    {
        $this->requireAtLeastOneSite();

        $site = Craft::$app->getSites()->getAllSites()[0];

        self::assertSame($site->id, SiteLanguageHelper::getSiteIdForLanguage($site->language));
    }

    public function testLanguageMatchIsCaseInsensitive(): void
    {
        $this->requireAtLeastOneSite();

        $site = Craft::$app->getSites()->getAllSites()[0];

        self::assertSame($site->id, SiteLanguageHelper::getSiteIdForLanguage(strtoupper($site->language)));
    }

    public function testUnknownLanguageFallsBackToPrimarySite(): void
    {
        $this->requireAtLeastOneSite();

        $primarySite = Craft::$app->getSites()->getPrimarySite();

        self::assertSame($primarySite->id, SiteLanguageHelper::getSiteIdForLanguage('__tm_missing_language__'));
    }
}
