<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Trait for consistent logging across services
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\traits;

use Craft;

/**
 * Logging trait for Translation Manager
 * Provides consistent logging to the dedicated translation-manager.log file
 */
trait LoggingTrait
{
    /**
     * Log an info message
     */
    protected function logInfo(string $message, array $params = []): void
    {
        Craft::info($this->formatMessage($message, $params), static::class);
    }
    
    /**
     * Log a warning message
     */
    protected function logWarning(string $message, array $params = []): void
    {
        Craft::warning($this->formatMessage($message, $params), static::class);
    }
    
    /**
     * Log an error message
     */
    protected function logError(string $message, array $params = []): void
    {
        Craft::error($this->formatMessage($message, $params), static::class);
    }
    
    /**
     * Format message with parameters
     */
    private function formatMessage(string $message, array $params = []): string
    {
        if (empty($params)) {
            return $message;
        }
        
        // Add parameters as JSON if provided
        return $message . ' | ' . json_encode($params);
    }
}