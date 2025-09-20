<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Base class for all translation integrations
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\integrations;

use craft\base\Component;
use lindemannrock\translationmanager\interfaces\TranslationIntegrationInterface;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\traits\LoggingTrait;

/**
 * Base Integration Class
 *
 * Provides common functionality for all translation integrations
 */
abstract class BaseIntegration extends Component implements TranslationIntegrationInterface
{
    use LoggingTrait;

    /**
     * @var bool Whether this integration is enabled
     */
    public bool $enabled = true;

    /**
     * @var array Integration-specific configuration
     */
    public array $config = [];

    /**
     * Get the translations service
     */
    protected function getTranslationsService()
    {
        return TranslationManager::getInstance()->translations;
    }

    /**
     * Create or update a translation with proper logging
     *
     * @param string $text The text to translate
     * @param string $context The translation context/key
     * @return mixed The created/updated translation record
     */
    protected function createTranslation(string $text, string $context)
    {
        $integrationName = $this->getName();
        $this->logInfo("Capturing {$integrationName} translation: '{$text}' ({$context})");

        return $this->getTranslationsService()->createOrUpdateTranslation($text, $context);
    }

    /**
     * Mark translations as unused with proper logging
     *
     * @param array $translationIds Array of translation IDs to mark as unused
     * @return int Number of translations marked as unused
     */
    protected function markTranslationsUnused(array $translationIds): int
    {
        $marked = 0;

        foreach ($translationIds as $id) {
            $record = \lindemannrock\translationmanager\records\TranslationRecord::findOne($id);
            if ($record && $record->status !== 'unused') {
                $record->status = 'unused';
                if ($record->save()) {
                    $marked++;
                    $this->logInfo("Marked translation as unused", [
                        'id' => $id,
                        'key' => $record->translationKey,
                        'context' => $record->context,
                        'integration' => $this->getName()
                    ]);
                }
            }
        }

        return $marked;
    }

    /**
     * Get translations managed by this integration
     *
     * @param array $criteria Additional search criteria
     * @return array Translation records
     */
    protected function getIntegrationTranslations(array $criteria = []): array
    {
        // Add integration-specific filtering
        $criteria['type'] = $this->getTranslationType();

        return $this->getTranslationsService()->getTranslations($criteria);
    }

    /**
     * Get the translation type identifier for this integration
     * Used for filtering translations by integration type
     */
    abstract protected function getTranslationType(): string;

    /**
     * Default implementation returns empty config schema
     * Override in specific integrations to provide settings
     */
    public function getConfigSchema(): array
    {
        return [
            'enabled' => [
                'type' => 'boolean',
                'label' => "Enable {$this->getName()} Integration",
                'default' => true,
            ]
        ];
    }

    /**
     * Default validation accepts any config
     * Override for specific validation rules
     */
    public function validateConfig(array $config): array
    {
        return [];
    }

    /**
     * Default statistics implementation
     * Override for integration-specific stats
     */
    public function getStatistics(): array
    {
        $translations = $this->getIntegrationTranslations();

        return [
            'name' => $this->getName(),
            'total' => count($translations),
            'translated' => count(array_filter($translations, fn($t) => $t['status'] === 'translated')),
            'pending' => count(array_filter($translations, fn($t) => $t['status'] === 'pending')),
            'unused' => count(array_filter($translations, fn($t) => $t['status'] === 'unused')),
        ];
    }
}