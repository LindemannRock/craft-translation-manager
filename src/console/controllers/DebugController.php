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

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use lindemannrock\translationmanager\records\TranslationRecord;
use yii\console\ExitCode;

/**
 * Debug commands
 */
class DebugController extends Controller
{
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
        $results = $query->where([
            'or',
            ['like', 'englishText', $search],
            ['like', 'arabicText', $search],
            ['like', 'context', $search],
        ])->all();
        
        if (empty($results)) {
            $this->stdout("No results found.\n", Console::FG_RED);
            
            // Try partial match
            $this->stdout("\nTrying partial matches...\n", Console::FG_YELLOW);
            $words = explode(' ', $search);
            foreach ($words as $word) {
                if (strlen($word) > 3) { // Skip short words
                    $partial = TranslationRecord::find()
                        ->where(['like', 'englishText', $word])
                        ->limit(5)
                        ->all();
                    
                    if (!empty($partial)) {
                        $this->stdout("\nFound with '{$word}':\n", Console::FG_GREEN);
                        foreach ($partial as $result) {
                            $this->stdout("- {$result->englishText}\n");
                        }
                    }
                }
            }
        } else {
            $this->stdout("\nFound " . count($results) . " result(s):\n", Console::FG_GREEN);
            
            foreach ($results as $result) {
                $this->stdout("\n--- Translation #{$result->id} ---\n", Console::FG_CYAN);
                $this->stdout("English: {$result->englishText}\n");
                $this->stdout("Arabic: {$result->arabicText}\n");
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
        
        $results = TranslationRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();
        
        foreach ($results as $result) {
            $this->stdout("\n--- Translation #{$result->id} ---\n", Console::FG_CYAN);
            $this->stdout("English: {$result->englishText}\n");
            $this->stdout("Context: {$result->context}\n");
            $this->stdout("Created: {$result->dateCreated}\n");
        }
        
        return ExitCode::OK;
    }
}