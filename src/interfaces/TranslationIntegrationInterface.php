<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Generic interface for third-party plugin integrations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\interfaces;

/**
 * Translation Integration Interface
 *
 * Defines the contract for all third-party plugin integrations with Translation Manager
 *
 * @since 1.5.0
 */
interface TranslationIntegrationInterface
{
    /**
     * Get the integration name (e.g., 'formie', 'commerce', 'seomatic')
     */
    public function getName(): string;

    /**
     * Get the plugin handle this integration supports
     */
    public function getPluginHandle(): string;

    /**
     * Check if the target plugin is installed and compatible
     */
    public function isAvailable(): bool;

    /**
     * Register event hooks and listeners for the target plugin
     */
    public function registerHooks(): void;

    /**
     * Capture all translatable content from an element/model
     *
     * @param mixed $element The element to extract translations from
     * @return array Array of captured translation data
     */
    public function captureTranslations($element): array;

    /**
     * Check usage of existing translations and mark unused ones
     * Called after content changes to clean up orphaned translations
     */
    public function checkUsage(): void;

    /**
     * Get all translatable fields/content types this integration supports
     *
     * @return array List of supported content types
     */
    public function getSupportedContentTypes(): array;

    /**
     * Get configuration schema for this integration
     * Used to generate settings UI and validation
     *
     * @return array Configuration schema
     */
    public function getConfigSchema(): array;

    /**
     * Validate integration-specific configuration
     *
     * @param array $config Configuration to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateConfig(array $config): array;

    /**
     * Get statistics for this integration (translation counts, etc.)
     *
     * @return array Statistics data
     */
    public function getStatistics(): array;
}
