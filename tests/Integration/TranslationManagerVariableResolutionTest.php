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
use craft\models\Site;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\variables\TranslationManagerVariable;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pins current-site Twig lookup to the mapped language and active site category.
 *
 * @since 5.35.0
 */
final class TranslationManagerVariableResolutionTest extends TestCase
{
    private ?Site $originalSite = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSite = Craft::$app->getSites()->getCurrentSite();
    }

    protected function tearDown(): void
    {
        if ($this->originalSite !== null) {
            Craft::$app->getSites()->setCurrentSite($this->originalSite);
        }

        parent::tearDown();
    }

    public function testCurrentMappedSiteUsesItsExactCategoryRowRegardlessOfLanguageOrder(): void
    {
        $sites = $this->requiredSitesByLanguage(['en', 'ar', 'en-US']);
        $settings = $this->settings();
        $category = 'tm-test-variable-' . bin2hex(random_bytes(4));
        $settings->sourceLanguage = 'en';
        $settings->translationCategory = $category;
        $settings->translationCategories = [['key' => $category, 'enabled' => true]];
        $settings->localeMapping = [[
            'source' => 'en-US',
            'destination' => 'fr',
            'enabled' => true,
        ]];

        $variable = new TranslationManagerVariable();
        $source = self::MARKER . 'variable_current_' . bin2hex(random_bytes(4));

        Craft::$app->getSites()->setCurrentSite($sites['en']);
        self::assertSame($source, $variable->t($source));

        $languages = TranslationManager::getInstance()->getUniqueLanguages();
        self::assertSame(['en', 'ar', 'fr'], $languages);
        self::assertNotSame('ar', $languages[0], 'The Arabic lookup must not depend on the first configured language.');

        $this->setTranslation($source, $category, 'ar', 'الترجمة العربية');
        $this->setTranslation($source, $category, 'fr', 'Traduction française');

        Craft::$app->getSites()->setCurrentSite($sites['ar']);
        self::assertSame('الترجمة العربية', $variable->t($source));
        self::assertTrue($variable->hasTranslation($source));

        Craft::$app->getSites()->setCurrentSite($sites['en-US']);
        self::assertSame('Traduction française', $variable->t($source));
        self::assertTrue($variable->hasTranslation($source));

        Craft::$app->getSites()->setCurrentSite($sites['en']);
        self::assertSame($source, $variable->t($source), 'Source-language sites keep the source text fallback.');
    }

    public function testExplicitContextCaptureAndLookupShareTheExactCurrentLanguageContract(): void
    {
        $sites = $this->requiredSitesByLanguage(['en', 'ar', 'en-US']);
        $settings = $this->settings();
        $category = 'tm-test-variable-context-' . bin2hex(random_bytes(4));
        $otherCategory = $category . '-other';
        $settings->sourceLanguage = 'en';
        $settings->translationCategory = $category;
        $settings->translationCategories = [
            ['key' => $category, 'enabled' => true],
            ['key' => $otherCategory, 'enabled' => true],
        ];
        $settings->localeMapping = [[
            'source' => 'en-US',
            'destination' => 'fr',
            'enabled' => true,
        ]];

        $variable = new TranslationManagerVariable();
        $source = self::MARKER . 'variable_context_' . bin2hex(random_bytes(4));

        Craft::$app->getSites()->setCurrentSite($sites['en']);
        self::assertSame($source, $variable->t($source, 'checkout'));

        $rows = $this->fetchRowsForSource($source);
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            self::assertSame('site.checkout', $row['context']);
            self::assertSame($category, $row['category']);
        }

        $this->setTranslation($source, $category, 'ar', 'Arabic checkout');
        $this->createTranslation($source, $otherCategory, 'fr', 'Wrong category');

        Craft::$app->getSites()->setCurrentSite($sites['ar']);
        self::assertSame('Arabic checkout', $variable->t($source, 'checkout'));
        self::assertTrue($variable->hasTranslation($source, 'checkout'));

        Craft::$app->getSites()->setCurrentSite($sites['en-US']);
        self::assertSame($source, $variable->t($source, 'checkout'));
        self::assertFalse($variable->hasTranslation($source, 'checkout'));

        $missing = self::MARKER . 'variable_missing_' . bin2hex(random_bytes(4));
        self::assertFalse($variable->hasTranslation($missing, 'checkout'));
        self::assertSame($missing, $variable->t($missing, 'checkout'));
        self::assertFalse($variable->hasTranslation($missing, 'checkout'));
    }

    #[DataProvider('translationValueProvider')]
    public function testTwigTranslationPresenceUsesTrimBasedSemantics(
        ?string $value,
        bool $expectedPresent,
    ): void {
        $sites = $this->requiredSitesByLanguage(['en', 'ar']);
        $settings = $this->settings();
        $category = 'tm-test-variable-value-' . bin2hex(random_bytes(4));
        $settings->sourceLanguage = 'en';
        $settings->translationCategory = $category;
        $settings->translationCategories = [['key' => $category, 'enabled' => true]];
        $settings->localeMapping = [];

        $variable = new TranslationManagerVariable();
        $source = self::MARKER . 'variable_value_' . bin2hex(random_bytes(4));

        Craft::$app->getSites()->setCurrentSite($sites['en']);
        self::assertSame($source, $variable->t($source));
        $this->setTranslation($source, $category, 'ar', $value);

        Craft::$app->getSites()->setCurrentSite($sites['ar']);
        self::assertSame($expectedPresent ? $value : $source, $variable->t($source));
        self::assertSame($expectedPresent, $variable->hasTranslation($source));
    }

    /**
     * @return array<string,array{0:?string,1:bool}>
     */
    public static function translationValueProvider(): array
    {
        return [
            'zero' => ['0', true],
            'normal text' => ['Translated text', true],
            'empty string' => ['', false],
            'whitespace' => [" \t\n", false],
            'null' => [null, false],
        ];
    }

    /**
     * @param string[] $languages
     * @return array<string,Site>
     */
    private function requiredSitesByLanguage(array $languages): array
    {
        $sites = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $sites[$site->language] = $site;
        }

        foreach ($languages as $language) {
            if (!isset($sites[$language])) {
                self::markTestSkipped("Test requires a {$language} site.");
            }
        }

        return $sites;
    }

    private function setTranslation(string $source, string $category, string $language, ?string $value): void
    {
        $record = TranslationRecord::findOne([
            'sourceHash' => md5($source),
            'category' => $category,
            'language' => $language,
        ]);
        self::assertNotNull($record);

        $record->translation = $value;
        $record->status = 'translated';
        self::assertTrue($record->save(false));
    }

    private function createTranslation(string $source, string $category, string $language, ?string $value): void
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $record = new TranslationRecord();
        $record->source = $source;
        $record->sourceHash = md5($source);
        $record->context = 'site.checkout';
        $record->category = $category;
        $record->translationKey = $source;
        $record->translation = $value;
        $record->siteId = (int)$site->id;
        $record->language = $language;
        $record->status = 'translated';
        $record->translationOrigin = 'manual';
        $record->usageCount = 1;
        self::assertTrue($record->save(), json_encode($record->getErrors()));
    }
}
