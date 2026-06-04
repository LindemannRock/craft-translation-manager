<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Debug controller for AI provider testing
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\translationmanager\TranslationManager;
use yii\console\ExitCode;

/**
 * Debug commands
 *
 * @since 1.0.0
 */
class DebugController extends Controller
{
    /**
     * @var string|null Provider handle to test; null uses settings default.
     * @since 5.25.0
     */
    public ?string $provider = null;

    /**
     * @var string Target language for the sample translation.
     * @since 5.25.0
     */
    public string $targetLanguage = 'de';

    /**
     * @var string Sample text to translate.
     * @since 5.25.0
     */
    public string $text = 'Welcome to our agency website.';

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'test-ai') {
            $options[] = 'provider';
            $options[] = 'targetLanguage';
            $options[] = 'text';
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases(): array
    {
        $aliases = parent::optionAliases();
        $aliases['target-language'] = 'targetLanguage';
        return $aliases;
    }

    /**
     * Test configured AI provider or explicit provider with a live API call.
     *
     * Usage:
     * - php craft translation-manager/debug/test-ai
     * - php craft translation-manager/debug/test-ai --provider=openai
     * - php craft translation-manager/debug/test-ai --provider=gemini --target-language=ar --text="Welcome to our agency"
     *
     * @since 5.22.0
     */
    public function actionTestAi(): int
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $selectedProvider = $this->provider ?? $settings->aiProvider;

        $this->stdout("AI provider test\n", Console::FG_YELLOW);
        $this->stdout("Provider: {$selectedProvider}\n", Console::FG_CYAN);
        $this->stdout("Target language: {$this->targetLanguage}\n", Console::FG_CYAN);
        $this->stdout("Source language: {$settings->sourceLanguage}\n\n", Console::FG_CYAN);

        try {
            $test = TranslationManager::getInstance()->ai->testProvider($this->provider);

            $this->stdout("Connection: ", Console::FG_YELLOW);
            if ($test['success']) {
                $this->stdout("OK\n", Console::FG_GREEN);
            } else {
                $this->stdout("FAILED\n", Console::FG_RED);
                $this->stderr("Message: {$test['message']}\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout("Model: {$test['model']}\n", Console::FG_CYAN);
            $this->stdout("Provider reply: {$test['message']}\n\n", Console::FG_GREY);

            $translated = TranslationManager::getInstance()->ai->translateText(
                $this->text,
                $settings->sourceLanguage,
                $this->targetLanguage,
                $this->provider,
            );

            $this->stdout("Sample translation\n", Console::FG_YELLOW);
            $this->stdout("Input: {$this->text}\n", Console::FG_CYAN);
            $this->stdout("Output: {$translated}\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("AI test failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
