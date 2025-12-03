<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\twigextensions;

use lindemannrock\translationmanager\TranslationManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Plugin Name Twig Extension
 *
 * Provides centralized access to plugin name variations in Twig templates.
 *
 * Usage in templates:
 * - {{ translationHelper.displayName }}             // "Translation" (singular, no Manager)
 * - {{ translationHelper.pluralDisplayName }}       // "Translations" (plural, no Manager)
 * - {{ translationHelper.fullName }}                // "Translation Manager" (as configured)
 * - {{ translationHelper.lowerDisplayName }}        // "translation" (lowercase singular)
 * - {{ translationHelper.pluralLowerDisplayName }}  // "translations" (lowercase plural)
 * @since 1.0.0
 */
class PluginNameExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Translation Manager - Plugin Name Helper';
    }

    /**
     * Make plugin name helper available as global Twig variable
     *
     * @return array
     */
    public function getGlobals(): array
    {
        return [
            'translationHelper' => new PluginNameHelper(),
        ];
    }
}

/**
 * Plugin Name Helper
 *
 * Helper class that exposes Settings methods as properties for clean Twig syntax.
 */
class PluginNameHelper
{
    /**
     * Get display name (singular, without "Manager")
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return TranslationManager::$plugin->getSettings()->getDisplayName();
    }

    /**
     * Get plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralDisplayName(): string
    {
        return TranslationManager::$plugin->getSettings()->getPluralDisplayName();
    }

    /**
     * Get full plugin name (as configured)
     *
     * @return string
     */
    public function getFullName(): string
    {
        return TranslationManager::$plugin->getSettings()->getFullName();
    }

    /**
     * Get lowercase display name (singular, without "Manager")
     *
     * @return string
     */
    public function getLowerDisplayName(): string
    {
        return TranslationManager::$plugin->getSettings()->getLowerDisplayName();
    }

    /**
     * Get lowercase plural display name (without "Manager")
     *
     * @return string
     */
    public function getPluralLowerDisplayName(): string
    {
        return TranslationManager::$plugin->getSettings()->getPluralLowerDisplayName();
    }

    /**
     * Magic getter to allow property-style access in Twig
     * Enables: {{ translationHelper.displayName }} instead of {{ translationHelper.getDisplayName() }}
     *
     * @param string $name
     * @return string|null
     */
    public function __get(string $name): ?string
    {
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return null;
    }
}
