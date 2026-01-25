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
     *
     * @return string
     * @since 1.5.0
     */
    public function getName(): string;

    /**
     * Get the plugin handle this integration supports
     *
     * @return string
     * @since 1.5.0
     */
    public function getPluginHandle(): string;

    /**
     * Check if the target plugin is installed and compatible
     *
     * @return bool
     * @since 1.5.0
     */
    public function isAvailable(): bool;

    /**
     * Register event hooks and listeners for the target plugin
     *
     * @since 1.5.0
     */
    public function registerHooks(): void;

    /**
     * Capture all translatable content from an element/model
     *
     * @param mixed $element The element to extract translations from
     * @return array Array of captured translation data
     * @since 1.5.0
     */
    public function captureTranslations($element): array;

    /**
     * Check usage of existing translations and mark unused ones
     * Called after content changes to clean up orphaned translations
     *
     * @since 1.5.0
     */
    public function checkUsage(): void;

    /**
     * Get all translatable fields/content types this integration supports
     *
     * @return array List of supported content types
     * @since 1.5.0
     */
    public function getSupportedContentTypes(): array;

    /**
     * Get configuration schema for this integration
     * Used to generate settings UI and validation
     *
     * @return array Configuration schema
     * @since 1.5.0
     */
    public function getConfigSchema(): array;

    /**
     * Validate integration-specific configuration
     *
     * @param array $config Configuration to validate
     * @return array Validation errors (empty if valid)
     * @since 1.5.0
     */
    public function validateConfig(array $config): array;

    /**
     * Get statistics for this integration (translation counts, etc.)
     *
     * @return array Statistics data
     * @since 1.5.0
     */
    public function getStatistics(): array;
}
