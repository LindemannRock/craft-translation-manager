<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for managing third-party plugin integrations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use Craft;
use craft\base\Component;
use lindemannrock\translationmanager\interfaces\TranslationIntegrationInterface;
use lindemannrock\translationmanager\traits\LoggingTrait;

/**
 * Integration Registry Service
 * 
 * Manages all third-party plugin integrations for Translation Manager
 */
class IntegrationService extends Component
{
    use LoggingTrait;

    /**
     * @var TranslationIntegrationInterface[] Registered integrations
     */
    private array $_integrations = [];

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
        
        $this->logInfo('Initializing Translation Manager integrations');
        
        // Auto-discover and register built-in integrations
        $this->discoverBuiltInIntegrations();
        
        // Allow third-party plugins to register integrations via events
        $this->triggerRegistrationEvent();
        
        // Initialize enabled integrations
        $this->initializeIntegrations();
        
        $this->logInfo('Integration system initialized', [
            'registered' => count($this->_integrations),
            'enabled' => count($this->getEnabledIntegrations())
        ]);
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
        
        $this->logInfo("Registered integration: {$name}", [
            'class' => get_class($integration),
            'available' => $integration->isAvailable()
        ]);
    }

    /**
     * Get a registered integration by name
     */
    public function get(string $name): ?TranslationIntegrationInterface
    {
        return $this->_integrations[$name] ?? null;
    }

    /**
     * Get all registered integrations
     * 
     * @return TranslationIntegrationInterface[]
     */
    public function getAll(): array
    {
        return $this->_integrations;
    }

    /**
     * Get only enabled and available integrations
     * 
     * @return TranslationIntegrationInterface[]
     */
    public function getEnabledIntegrations(): array
    {
        return array_filter($this->_integrations, function($integration) {
            return $integration->isAvailable() && $this->isIntegrationEnabled($integration->getName());
        });
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
        $defaultSettings = [];
        foreach ($this->_integrations as $name => $integration) {
            $defaultSettings[$name] = [
                'enabled' => true,
                'name' => ucfirst($name),
                'pluginHandle' => $integration->getPluginHandle(),
                'available' => $integration->isAvailable(),
                'config' => []
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
                'unused' => 0
            ]
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
            if ($integration->isAvailable() && $this->isIntegrationEnabled($name)) {
                try {
                    $integration->registerHooks();
                    $this->logInfo("Initialized integration: {$name}");
                } catch (\Exception $e) {
                    $this->logError("Failed to initialize integration {$name}: " . $e->getMessage());
                }
            } else {
                $this->logInfo("Skipped integration: {$name}", [
                    'available' => $integration->isAvailable(),
                    'enabled' => $this->isIntegrationEnabled($name)
                ]);
            }
        }
    }
}