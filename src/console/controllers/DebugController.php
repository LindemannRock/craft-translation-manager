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
     * Test configured AI provider or explicit provider with a live API call.
     *
     * Usage:
     * - php craft translation-manager/debug/test-ai
     * - php craft translation-manager/debug/test-ai openai
     * - php craft translation-manager/debug/test-ai gemini ar "Welcome to our agency"
     *
     * @since 5.22.0
     */
    public function actionTestAi(
        ?string $provider = null,
        string $targetLanguage = 'de',
        string $text = 'Welcome to our agency website.',
    ): int {
        $settings = TranslationManager::getInstance()->getSettings();
        $selectedProvider = $provider ?? $settings->aiProvider;

        $this->stdout("AI provider test\n", Console::FG_YELLOW);
        $this->stdout("Provider: {$selectedProvider}\n", Console::FG_CYAN);
        $this->stdout("Target language: {$targetLanguage}\n", Console::FG_CYAN);
        $this->stdout("Source language: {$settings->sourceLanguage}\n\n", Console::FG_CYAN);

        try {
            $test = TranslationManager::getInstance()->ai->testProvider($provider);

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
                $text,
                $settings->sourceLanguage,
                $targetLanguage,
                $provider,
            );

            $this->stdout("Sample translation\n", Console::FG_YELLOW);
            $this->stdout("Input: {$text}\n", Console::FG_CYAN);
            $this->stdout("Output: {$translated}\n", Console::FG_GREEN);

            return ExitCode::OK;
        } catch (\Throwable $e) {
            $this->stderr("AI test failed: {$e->getMessage()}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
