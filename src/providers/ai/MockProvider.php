<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Mock provider adapter for local testing
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\providers\ai;

use lindemannrock\translationmanager\interfaces\AiTranslationProviderInterface;

/**
 * Mock provider
 *
 * @since 5.22.0
 */
class MockProvider implements AiTranslationProviderInterface
{
    public function getHandle(): string
    {
        return 'mock';
    }

    public function getDisplayName(): string
    {
        return 'Mock Provider';
    }

    public function testConnection(): array
    {
        return [
            'success' => true,
            'provider' => $this->getDisplayName(),
            'model' => 'mock-v1',
            'message' => 'TM_OK',
        ];
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        return sprintf('[MOCK %s->%s] %s', $sourceLanguage, $targetLanguage, $text);
    }
}
