<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for managing third-party plugin integrations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use craft\base\Component;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\interfaces\TranslationIntegrationInterface;

/**
 * Integration Registry Service
 *
 * Manages all third-party plugin integrations for Translation Manager
 *
 * @since 1.5.0
 */
class IntegrationService extends Component
{
    use LoggingTrait;

    /**
     * @var TranslationIntegrationInterface[] Registered integrations
     */
    private array $_integrations = [];

    /**
     * @var bool Track if service has been initialized to prevent duplicate initialization
     */
    private bool $_initialized = false;

    /**
     * @var bool Track if integrations have been loaded to implement lazy loading
     */
    private bool $_integrationsLoaded = false;

    /**
     * @var bool Track if we've logged the initial setup to reduce log spam
     */
    private static bool $_hasLoggedSetup = false;

    /**
     * @var array<string, true> Integration names whose hooks have been registered this request,
     * keyed by name. Prevents the eager init-path and the lazy load-path from registering the
     * same event listeners twice (which would fire form save/delete capture more than once).
     */
    private static array $_registeredHookNames = [];


    /**
     * @var array Built-in integration class mappings
     */
    private array $_builtInIntegrations = [
        'formie' => \lindemannrock\translationmanager\integrations\FormieIntegration::class,
        // Future integrations:
        // 'commerce' => \lindemannrock\translationmanager\integrations\CommerceIntegration::class,
        // 'seomatic' => \lindemannrock\translationmanager\integrations\SeomaticIntegration::class,
    ];

    /**
     * Initialize and register all available integrations
     */
    public function init(): void
    {
        parent::init();

        // Lightweight initialization - just register event handlers
        // Heavy integration loading happens lazily when needed
        $this->registerEventHandlers();

        $this->_initialized = true;
    }

    /**
     * Register essential event handlers (lightweight)
     */
    private function registerEventHandlers(): void
    {
        // Register FormieIntegration event handlers directly
        if (PluginHelper::isPluginEnabled('formie')) {
            $this->registerIntegrationHooks('formie', new \lindemannrock\translationmanager\integrations\FormieIntegration());
        }
    }

    /**
     * Register an integration's event hooks exactly once per request.
     *
     * Both the eager init path (registerEventHandlers) and the lazy load path
     * (initializeIntegrations) can reach the same integration; without this guard
     * the underlying Event::on() listeners would be attached twice and fire on
     * every form save/delete more than once.
     */
    private function registerIntegrationHooks(string $name, TranslationIntegrationInterface $integration): void
    {
        if (isset(self::$_registeredHookNames[$name])) {
            return;
        }

        $integration->registerHooks();
        self::$_registeredHookNames[$name] = true;
    }

    /**
     * Register a new integration
     *
     * @param string $name Integration name
     * @param string|TranslationIntegrationInterface $integration Integration class or instance
     * @param array $config Optional configuration
     */
    public function register(string $name, $integration, array $config = []): void
    {
        if (is_string($integration)) {
            // Create instance from class name
            $integration = new $integration($config);
        }

        if (!$integration instanceof TranslationIntegrationInterface) {
            throw new \InvalidArgumentException("Integration must implement TranslationIntegrationInterface");
        }

        $this->_integrations[$name] = $integration;
    }

    /**
     * Get a registered integration by name
     */
    public function get(string $name): ?TranslationIntegrationInterface
    {
        $this->ensureIntegrationsLoaded();
        return $this->_integrations[$name] ?? null;
    }

    /**
     * Get all registered integrations
     *
     * @return TranslationIntegrationInterface[]
     */
    public function getAll(): array
    {
        $this->ensureIntegrationsLoaded();
        return $this->_integrations;
    }

    /**
     * Get only enabled and available integrations
     *
     * @return TranslationIntegrationInterface[]
     */
    public function getEnabledIntegrations(): array
    {
        $this->ensureIntegrationsLoaded();
        return array_filter($this->_integrations, function($integration) {
            return $integration->isAvailable() && $this->isIntegrationEnabled($integration->getName());
        });
    }

    /**
     * Get registered integrations for a source type.
     *
     * By default this includes registered integrations even when their target
     * plugin is currently unavailable or disabled, so stale translation rows
     * remain filterable and cleanable.
     *
     * @return TranslationIntegrationInterface[]
     */
    public function getIntegrationsBySourceType(string $sourceType, bool $enabledOnly = false): array
    {
        $this->ensureIntegrationsLoaded();

        return array_filter(
            $enabledOnly ? $this->getEnabledIntegrations() : $this->_integrations,
            static fn(TranslationIntegrationInterface $integration): bool => $integration->getSourceType() === $sourceType,
        );
    }

    /**
     * Get context prefixes for registered integrations.
     *
     * @return string[]
     */
    public function getContextPrefixes(?string $sourceType = null, bool $enabledOnly = false): array
    {
        $integrations = $sourceType === null
            ? ($enabledOnly ? $this->getEnabledIntegrations() : $this->getAll())
            : $this->getIntegrationsBySourceType($sourceType, $enabledOnly);

        $prefixes = [];
        foreach ($integrations as $integration) {
            $prefixes[] = $integration->getContextPrefix();
        }

        return array_values(array_unique(array_filter($prefixes)));
    }

    /**
     * Get context prefixes that do not belong to site translations.
     *
     * @return string[]
     */
    public function getIntegrationContextPrefixes(bool $enabledOnly = false): array
    {
        return $this->getContextPrefixes(null, $enabledOnly);
    }

    /**
     * Resolve the translation category for a context, if it belongs to an integration.
     */
    public function getCategoryForContext(string $context): ?string
    {
        return $this->getIntegrationForContext($context)?->getCategory();
    }

    /**
     * Resolve the context prefix for an integration category.
     */
    public function getContextPrefixForCategory(string $category): ?string
    {
        return $this->getIntegrationForCategory($category)?->getContextPrefix();
    }

    /**
     * Resolve the integration for a translation context.
     */
    public function getIntegrationForContext(string $context): ?TranslationIntegrationInterface
    {
        foreach ($this->getAll() as $integration) {
            $prefix = $integration->getContextPrefix();

            if ($context === $prefix || str_starts_with($context, $prefix . '.')) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Resolve the integration for a translation category.
     */
    public function getIntegrationForCategory(string $category): ?TranslationIntegrationInterface
    {
        foreach ($this->getAll() as $integration) {
            if ($integration->getCategory() === $category) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Check whether an integration category is registered and enabled.
     */
    public function isIntegrationCategoryEnabled(string $category): bool
    {
        foreach ($this->getAll() as $integration) {
            if ($integration->getCategory() === $category) {
                return $this->isIntegrationEnabled($integration->getName());
            }
        }

        return false;
    }

    /**
     * Check if an integration is enabled in settings
     */
    public function isIntegrationEnabled(string $name): bool
    {
        $settings = \lindemannrock\translationmanager\TranslationManager::getInstance()->getSettings();
        
        // Check for integration-specific enable setting
        $enabledProperty = 'enable' . ucfirst($name) . 'Integration';
        
        if (property_exists($settings, $enabledProperty)) {
            return $settings->$enabledProperty;
        }

        // Check for dynamic integration settings
        $integrationSettings = $this->getIntegrationSettings();
        if (isset($integrationSettings[$name])) {
            return $integrationSettings[$name]['enabled'] ?? true;
        }

        // Default to enabled if no specific setting exists
        return true;
    }

    /**
     * Get dynamic integration settings from database/config
     */
    public function getIntegrationSettings(): array
    {
        $settings = \lindemannrock\translationmanager\TranslationManager::getInstance()->getSettings();

        // Check if we have dynamic integration settings
        if (property_exists($settings, 'integrationSettings') && is_array($settings->integrationSettings)) {
            return $settings->integrationSettings;
        }

        // Build default settings for discovered integrations
        $this->ensureIntegrationsLoaded();
        $defaultSettings = [];
        foreach ($this->_integrations as $name => $integration) {
            $defaultSettings[$name] = [
                'enabled' => true,
                'name' => ucfirst($name),
                'pluginHandle' => $integration->getPluginHandle(),
                'available' => $integration->isAvailable(),
                'config' => [],
            ];
        }

        return $defaultSettings;
    }

    /**
     * Save integration settings
     */
    public function saveIntegrationSettings(array $integrationSettings): bool
    {
        $settings = \lindemannrock\translationmanager\TranslationManager::getInstance()->getSettings();
        
        if (property_exists($settings, 'integrationSettings')) {
            $settings->integrationSettings = $integrationSettings;
            return $settings->saveToDatabase();
        }

        return false;
    }

    /**
     * Get combined statistics from all enabled integrations
     */
    public function getCombinedStatistics(): array
    {
        $combined = [
            'integrations' => [],
            'totals' => [
                'total' => 0,
                'translated' => 0,
                'pending' => 0,
                'unused' => 0,
            ],
        ];

        foreach ($this->getEnabledIntegrations() as $integration) {
            $stats = $integration->getStatistics();
            $combined['integrations'][$integration->getName()] = $stats;
            
            // Add to totals
            $combined['totals']['total'] += $stats['total'] ?? 0;
            $combined['totals']['translated'] += $stats['translated'] ?? 0;
            $combined['totals']['pending'] += $stats['pending'] ?? 0;
            $combined['totals']['unused'] += $stats['unused'] ?? 0;
        }

        return $combined;
    }

    /**
     * Trigger usage check across all enabled integrations
     */
    public function checkAllUsage(): void
    {
        foreach ($this->getEnabledIntegrations() as $integration) {
            try {
                $integration->checkUsage();
            } catch (\Exception $e) {
                $this->logError("Failed to check usage for {$integration->getName()}: " . $e->getMessage());
            }
        }
    }

    /**
     * Ensure integrations are loaded (lazy loading)
     */
    public function ensureIntegrationsLoaded(): void
    {
        if ($this->_integrationsLoaded) {
            $this->logDebug("IntegrationService: Already loaded, skipping");
            return;
        }

        $this->logInfo("IntegrationService: Loading integrations for the first time");

        $this->discoverBuiltInIntegrations();
        $this->triggerRegistrationEvent();
        $this->initializeIntegrations();

        $this->_integrationsLoaded = true;
        $this->logInfo("IntegrationService: Integration loading completed");
    }

    /**
     * Auto-discover built-in integrations
     */
    private function discoverBuiltInIntegrations(): void
    {
        foreach ($this->_builtInIntegrations as $name => $className) {
            if (class_exists($className)) {
                try {
                    $this->register($name, $className);
                } catch (\Exception $e) {
                    $this->logError("Failed to register built-in integration {$name}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Trigger event for third-party integration registration
     */
    private function triggerRegistrationEvent(): void
    {
        // TODO: Implement event system for third-party registration
        // Event::trigger(self::class, self::EVENT_REGISTER_INTEGRATIONS, new IntegrationRegistrationEvent([
        //     'registry' => $this
        // ]));
    }

    /**
     * Initialize all registered integrations
     */
    private function initializeIntegrations(): void
    {
        foreach ($this->_integrations as $name => $integration) {
            $available = $integration->isAvailable();
            $enabled = $this->isIntegrationEnabled($name);

            if ($available && $enabled) {
                try {
                    $this->registerIntegrationHooks($name, $integration);
                } catch (\Exception $e) {
                    // Always log errors
                    $this->logError("Failed to initialize integration {$name}: " . $e->getMessage());
                }
            }
        }
    }
}
