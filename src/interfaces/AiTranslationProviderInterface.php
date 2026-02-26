<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Interface for AI translation providers
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\interfaces;

/**
 * AI translation provider interface
 *
 * @since 5.22.0
 */
interface AiTranslationProviderInterface
{
    /**
     * Provider handle
     */
    public function getHandle(): string;

    /**
     * Human readable provider name
     */
    public function getDisplayName(): string;

    /**
     * Validate API connectivity and credentials.
     *
     * @return array{success: bool, provider: string, model: string, message: string}
     */
    public function testConnection(): array;

    /**
     * Translate plain text to target language.
     */
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string;
}
