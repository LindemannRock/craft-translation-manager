<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Translation element for Feed Me compatibility
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\elements;

use craft\base\Element;

/**
 * Translation element - a wrapper for Feed Me compatibility
 * 
 * This is not a full Craft element, just a minimal implementation
 * to allow Feed Me imports of translation data.
 */
class TranslationElement extends Element
{
    public ?int $translationId = null;
    public ?string $englishText = null;
    public ?string $arabicText = null;
    public ?string $context = null;
    public ?string $status = null;
    
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return \lindemannrock\translationmanager\TranslationManager::$plugin->getSettings()->getDisplayName();
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return \lindemannrock\translationmanager\TranslationManager::$plugin->getSettings()->getPluralDisplayName();
    }
    
    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return $this->englishText ?? 'Translation';
    }
    
    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        
        $rules[] = [['englishText'], 'required'];
        $rules[] = [['englishText', 'arabicText'], 'string', 'max' => 5000];
        $rules[] = [['status'], 'in', 'range' => ['pending', 'translated', 'unused', 'approved']];
        
        return $rules;
    }
}