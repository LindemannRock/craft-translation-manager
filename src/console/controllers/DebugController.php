<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Debug controller for testing search functionality
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\translationmanager\records\TranslationRecord;
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

    /**
     * Search for translations in the database
     *
     * @param string $search The search term
     */
    public function actionSearch(string $search): int
    {
        $this->stdout("Searching for: '{$search}'\n", Console::FG_YELLOW);
        
        // Direct database search
        $query = TranslationRecord::find();

        // Search in all fields
        /** @var TranslationRecord[] $results */
        $results = $query->where([
            'or',
            ['like', 'translationKey', $search],
            ['like', 'translation', $search],
            ['like', 'context', $search],
        ])->all();
        
        if (empty($results)) {
            $this->stdout("No results found.\n", Console::FG_RED);
            
            // Try partial match
            $this->stdout("\nTrying partial matches...\n", Console::FG_YELLOW);
            $words = explode(' ', $search);
            foreach ($words as $word) {
                if (strlen($word) > 3) { // Skip short words
                    /** @var TranslationRecord[] $partial */
                    $partial = TranslationRecord::find()
                        ->where(['like', 'translationKey', $word])
                        ->limit(5)
                        ->all();

                    if (!empty($partial)) {
                        $this->stdout("\nFound with '{$word}':\n", Console::FG_GREEN);
                        foreach ($partial as $result) {
                            $this->stdout("- {$result->translationKey}\n");
                        }
                    }
                }
            }
        } else {
            $this->stdout("\nFound " . count($results) . " result(s):\n", Console::FG_GREEN);
            
            foreach ($results as $result) {
                $this->stdout("\n--- Translation #{$result->id} ---\n", Console::FG_CYAN);
                $this->stdout("Key: {$result->translationKey}\n");
                $this->stdout("Translation: {$result->translation}\n");
                $this->stdout("Context: {$result->context}\n");
                $this->stdout("Status: {$result->status}\n");
            }
        }
        
        // Also check total count
        $total = TranslationRecord::find()->count();
        $this->stdout("\nTotal translations in database: {$total}\n", Console::FG_GREY);
        
        return ExitCode::OK;
    }
    
    /**
     * List recent translations
     */
    public function actionRecent(int $limit = 10): int
    {
        $this->stdout("Recent translations (limit: {$limit}):\n", Console::FG_YELLOW);

        /** @var TranslationRecord[] $results */
        $results = TranslationRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();
        
        foreach ($results as $result) {
            $this->stdout("\n--- Translation #{$result->id} ---\n", Console::FG_CYAN);
            $this->stdout("Key: {$result->translationKey}\n");
            $this->stdout("Context: {$result->context}\n");
            $this->stdout("Created: {$result->dateCreated}\n");
        }
        
        return ExitCode::OK;
    }
}
