<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * AI translation service with provider adapters
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use craft\base\Component;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\interfaces\AiTranslationProviderInterface;
use lindemannrock\translationmanager\providers\ai\AnthropicProvider;
use lindemannrock\translationmanager\providers\ai\GeminiProvider;
use lindemannrock\translationmanager\providers\ai\MockProvider;
use lindemannrock\translationmanager\providers\ai\OpenAiProvider;
use lindemannrock\translationmanager\TranslationManager;

/**
 * AI translation service
 *
 * @since 5.22.0
 */
class AiTranslationService extends Component
{
    use LoggingTrait;

    /**
     * @var array<string, AiTranslationProviderInterface>
     */
    private array $providers = [];

    public function init(): void
    {
        parent::init();
        $this->setLoggingHandle(TranslationManager::$plugin->id);
    }

    /**
     * Get available provider handles.
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return ['openai', 'gemini', 'anthropic', 'mock'];
    }

    /**
     * Resolve provider by explicit handle or configured default.
     */
    public function getProvider(?string $handle = null): AiTranslationProviderInterface
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $providerHandle = $handle ?? $settings->aiProvider;

        if (!in_array($providerHandle, $this->getAvailableProviders(), true)) {
            throw new \InvalidArgumentException("Unsupported AI provider: {$providerHandle}");
        }

        if (isset($this->providers[$providerHandle])) {
            return $this->providers[$providerHandle];
        }

        if ($providerHandle === 'mock') {
            $provider = new MockProvider();
            $this->providers[$providerHandle] = $provider;
            return $provider;
        }

        $apiKey = $settings->getResolvedAiApiKey($providerHandle);
        if ($apiKey === null) {
            throw new \RuntimeException("No API key configured for provider: {$providerHandle}");
        }
        $model = $settings->getResolvedAiModel($providerHandle);

        $provider = match ($providerHandle) {
            'openai' => new OpenAiProvider($apiKey, $model),
            'gemini' => new GeminiProvider($apiKey, $model),
            'anthropic' => new AnthropicProvider($apiKey, $model),
            default => throw new \InvalidArgumentException("Unsupported AI provider: {$providerHandle}"),
        };

        $this->providers[$providerHandle] = $provider;

        return $provider;
    }

    /**
     * Test configured or explicit provider connection.
     *
     * @return array{success: bool, provider: string, model: string, message: string}
     */
    public function testProvider(?string $handle = null): array
    {
        $provider = $this->getProvider($handle);
        return $provider->testConnection();
    }

    /**
     * Translate text via selected provider.
     */
    public function translateText(
        string $text,
        string $sourceLanguage,
        string $targetLanguage,
        ?string $providerHandle = null,
    ): string {
        $provider = $this->getProvider($providerHandle);
        $translated = $provider->translate($text, $sourceLanguage, $targetLanguage);

        $this->assertProtectedTokensPreserved($text, $translated, $provider->getHandle());
        $this->assertHtmlTagStructurePreserved($text, $translated, $provider->getHandle());

        return $translated;
    }

    /**
     * Ensure placeholders/tokens are preserved exactly in translated output.
     */
    private function assertProtectedTokensPreserved(string $source, string $translated, string $provider): void
    {
        $sourceTokens = $this->extractProtectedTokens($source);
        if ($sourceTokens === []) {
            return;
        }

        $translatedTokens = $this->extractProtectedTokens($translated);
        $sourceCounts = array_count_values($sourceTokens);
        $translatedCounts = array_count_values($translatedTokens);
        $mismatches = [];

        foreach ($sourceCounts as $token => $count) {
            $translatedCount = $translatedCounts[$token] ?? 0;
            if ($translatedCount !== $count) {
                $mismatches[] = $token;
            }
        }

        if ($mismatches !== []) {
            $tokenList = implode(', ', $mismatches);
            throw new \RuntimeException(
                "Translated text changed protected tokens ({$tokenList}). Provider: {$provider}"
            );
        }
    }

    /**
     * Extract tokens that must remain unchanged in translated strings.
     *
     * @return array<string>
     */
    private function extractProtectedTokens(string $text): array
    {
        $patterns = [
            '/\{[a-zA-Z0-9_]+\}/',      // {attribute}, {min}, {count}
            '/\$\w+/',                  // $jobTitle, $jobReferenceCode
            '/%(?:\d+\$)?[a-zA-Z]/',    // %s, %1$s, %d
            '/\{\{[^}]+\}\}/',          // Twig output tags
            '/\{%[^%]+%\}/',            // Twig logic tags
            '/`[^`]+`/',                // inline code blocks like `{e}`
        ];

        $tokens = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) === 1 || !empty($matches[0])) {
                foreach ($matches[0] as $match) {
                    $tokens[] = $match;
                }
            }
        }

        return $tokens;
    }

    /**
     * Ensure HTML tag structure remains compatible after translation.
     */
    private function assertHtmlTagStructurePreserved(string $source, string $translated, string $provider): void
    {
        $sourceTags = $this->extractHtmlTagCounts($source);
        if ($sourceTags === []) {
            return;
        }

        $translatedTags = $this->extractHtmlTagCounts($translated);
        $mismatches = [];

        foreach ($sourceTags as $tag => $sourceCount) {
            $translatedCount = $translatedTags[$tag] ?? 0;
            if ($translatedCount !== $sourceCount) {
                $mismatches[] = $tag;
            }
        }

        if ($mismatches !== []) {
            $tagList = implode(', ', $mismatches);
            throw new \RuntimeException(
                "Translated text changed HTML tag structure ({$tagList}). Provider: {$provider}"
            );
        }
    }

    /**
     * Count opening HTML tags by normalized name.
     *
     * @return array<string, int>
     */
    private function extractHtmlTagCounts(string $text): array
    {
        if (preg_match_all('/<\s*([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/u', $text, $matches) <= 0) {
            return [];
        }

        $counts = [];

        foreach ($matches[1] as $tagName) {
            $tag = strtolower($tagName);

            // Ignore markup that should not be translated structurally.
            if (in_array($tag, ['script', 'style'], true)) {
                continue;
            }

            // Count tags by appearance in opening form.
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }

        return $counts;
    }
}
