<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for importing translations from CSV files
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use craft\web\UploadedFile;
use craft\helpers\StringHelper;
use lindemannrock\translationmanager\TranslationManager;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
use yii\web\ForbiddenHttpException;

/**
 * CSV Import Controller
 */
class ImportController extends Controller
{
    /**
     * @var array|bool|int
     */
    protected array|bool|int $allowAnonymous = false;
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Ensure user is authenticated
        if (Craft::$app->getUser()->getIsGuest()) {
            throw new ForbiddenHttpException('User must be logged in.');
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Check which translations already exist
     */
    public function actionCheckExisting(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to check translations.');
        }
        
        $translations = Craft::$app->getRequest()->getBodyParam('translations', []);
        
        if (!is_array($translations)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid data format'
            ]);
        }
        
        
        $toImport = [];
        $toUpdate = [];
        $unchanged = [];
        $maliciousRows = [];
        $errors = [];
        
        foreach ($translations as $translation) {
            if (!isset($translation['english']) || empty($translation['english'])) {
                Craft::warning("Skipping translation with empty or missing English text", 'translation-manager');
                continue;
            }
            
            // Extract site information from CSV data
            $targetSiteId = null;
            if (isset($translation['siteId']) && !empty($translation['siteId'])) {
                $siteId = (int) $translation['siteId'];
                // Validate that site ID actually exists
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if ($site) {
                    $targetSiteId = $siteId;
                } else {
                    // Invalid site ID - add to errors array for preview
                    $errors[] = [
                        'english' => $translation['english'],
                        'arabic' => isset($translation['arabic']) ? $translation['arabic'] : '',
                        'context' => isset($translation['context']) ? $translation['context'] : 'site',
                        'siteId' => $siteId,
                        'siteLanguage' => 'unknown',
                        'error' => "Invalid site ID: {$siteId} does not exist"
                    ];
                    Craft::warning("Invalid site ID {$siteId} for translation '{$translation['english']}'", 'translation-manager');
                    continue; // Skip further processing
                }
            } elseif (isset($translation['siteLanguage']) && !empty($translation['siteLanguage'])) {
                $site = $this->findSiteByLanguage($translation['siteLanguage']);
                $targetSiteId = $site ? $site->id : null;
            }
            
            // Default to primary site if no site info provided
            if (!$targetSiteId) {
                $targetSiteId = Craft::$app->getSites()->getPrimarySite()->id;
            }
            
            // Store original values for comparison
            $originalEnglish = $translation['english'];
            $originalArabic = isset($translation['arabic']) ? $translation['arabic'] : '';
            $originalContext = isset($translation['context']) ? $translation['context'] : 'site';
            
            // Check for malicious content before sanitization
            $isMalicious = false;
            $detectedThreats = [];
            
            // Check all fields for dangerous patterns
            $fieldsToCheck = [
                'English' => $originalEnglish,
                'Arabic' => $originalArabic,
                'Context' => $originalContext
            ];
            
            foreach ($fieldsToCheck as $fieldName => $fieldValue) {
                if ($this->containsMaliciousContent($fieldValue, $threats)) {
                    $isMalicious = true;
                    $detectedThreats[$fieldName] = $threats;
                }
            }
            
            if ($isMalicious) {
                $maliciousRows[] = [
                    'english' => $originalEnglish,
                    'arabic' => $originalArabic,
                    'context' => $originalContext,
                    'threats' => $detectedThreats
                ];
                Craft::warning("Malicious content detected in translation '{$originalEnglish}': " . implode(', ', array_keys($detectedThreats)), 'translation-manager');
                continue; // Skip this row
            }
            
            // Same minimal sanitization as import
            $keyText = $translation['english'];
            $translationText = isset($translation['arabic']) ? $translation['arabic'] : '';
            $context = isset($translation['context']) ? StringHelper::stripHtml($translation['context']) : 'site';
            
            // Apply same dangerous pattern removal
            $dangerousPatterns = [
                '/<script[^>]*>.*?<\/script>/si',
                '/javascript:/i',
                '/on\w+\s*=/i',
                '/<iframe[^>]*>.*?<\/iframe>/si',
                '/<object[^>]*>.*?<\/object>/si',
                '/<embed[^>]*>/i',
                '/data:text\/html/i',
                '/vbscript:/i'
            ];
            
            foreach ($dangerousPatterns as $pattern) {
                $keyText = preg_replace($pattern, '', $keyText);
                $translationText = preg_replace($pattern, '', $translationText);
            }
            
            // Use context exactly as provided in CSV (don't normalize for re-import)
            if (empty($context)) {
                $context = 'site';
            }
            
            // Find existing by hash - match unique constraint (sourceHash + siteId)
            $sourceHash = md5($keyText);
            $existing = TranslationRecord::findOne([
                'sourceHash' => $sourceHash,
                'siteId' => $targetSiteId
            ]);
            
            
            
            if ($existing) {
                // Text already exists (possibly with different context)
                // Check if it's actually changing
                // Handle NULL vs empty string - treat both as equivalent
                $dbValue = $existing->translation;
                $csvValue = $translationText;
                
                // Normalize NULL to empty string for comparison
                $dbNormalized = $dbValue === null ? '' : $dbValue;
                $csvNormalized = $csvValue === null ? '' : $csvValue;
                
                $isIdentical = $dbNormalized === $csvNormalized;
                
                
                if ($isIdentical) {
                    $unchanged[] = [
                        'english' => $keyText,
                        'arabic' => $translationText,
                        'context' => $context,
                        'currentTranslation' => $existing->translation,
                        'currentStatus' => $existing->status,
                        'existingContext' => $existing->context,
                        'siteId' => $targetSiteId,
                        'siteLanguage' => $this->getSiteLanguageById($targetSiteId)
                    ];
                } else {
                    $toUpdate[] = [
                        'english' => $keyText,                    // Translation Key
                        'arabic' => $translationText,            // New Translation (from CSV)  
                        'currentTranslation' => $existing->translation ?? '', // Current Translation (from DB)
                        'context' => $context,
                        'currentStatus' => $existing->status,
                        'existingContext' => $existing->context,
                        'siteId' => $targetSiteId,
                        'siteLanguage' => $this->getSiteLanguageById($targetSiteId)
                    ];
                }
            } else {
                $toImport[] = [
                    'english' => $keyText,
                    'arabic' => $translationText,
                    'context' => $context,
                    'siteId' => $targetSiteId,
                    'siteLanguage' => $this->getSiteLanguageById($targetSiteId)
                ];
            }
        }
        
        return $this->asJson([
            'success' => true,
            'toImport' => $toImport,
            'toUpdate' => $toUpdate,
            'unchanged' => $unchanged,
            'malicious' => $maliciousRows,
            'errors' => $errors
        ]);
    }
    
    /**
     * Import CSV file
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        
        if (!$currentUser || !Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }
        
        $uploadedFile = UploadedFile::getInstanceByName('csvFile');
        
        // Validate file upload
        if (!$uploadedFile) {
            Craft::warning('Import failed: No file uploaded', 'translation-manager');
            return $this->asJson([
                'success' => false,
                'error' => 'No file uploaded'
            ]);
        }
        
        Craft::info("Import started: {$uploadedFile->name} ({$uploadedFile->size} bytes)", 'translation-manager');
        
        // Validate file type
        $extension = strtolower($uploadedFile->getExtension());
        if (!in_array($extension, ['csv', 'txt'])) {
            return $this->asJson([
                'success' => false,
                'error' => 'Only CSV files are allowed'
            ]);
        }
        
        // Validate file size (5MB limit)
        if ($uploadedFile->size > 5242880) {
            return $this->asJson([
                'success' => false,
                'error' => 'File size exceeds 5MB limit'
            ]);
        }
        
        // Validate MIME type
        $mimeType = $uploadedFile->getMimeType();
        $allowedMimeTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid file type'
            ]);
        }
        
        // Check if detailed results are requested
        $includeDetails = (bool) Craft::$app->getRequest()->getBodyParam('includeDetails', false);
        
        // Process CSV
        try {
            // Create backup before import if enabled
            $settings = TranslationManager::getInstance()->getSettings();
            $backupPath = null;
            if ($settings->backupEnabled && $settings->backupOnImport) {
                $backupPath = TranslationManager::getInstance()->backup->createBackup('before_import');
            }
            
            $results = $this->processCsv($uploadedFile->tempName, $includeDetails);
            
            // Delete temp file
            @unlink($uploadedFile->tempName);
            if ($settings->autoExport) {
                TranslationManager::getInstance()->export->exportAll();
            }
            
            // Save import history
            $history = new ImportHistoryRecord();
            $history->userId = $currentUser->id;
            $history->filename = $uploadedFile->name;
            $history->filesize = $uploadedFile->size;
            $history->imported = $results['imported'];
            $history->updated = $results['updated'];
            $history->skipped = $results['skipped'];
            $history->errors = !empty($results['errors']) ? json_encode($results['errors']) : null;
            $history->backupPath = $backupPath ? basename($backupPath) : null;
            $history->details = $includeDetails && isset($results['details']) ? json_encode($results['details']) : null;
            $history->save();
            
            // Log the import results
            Craft::info(
                sprintf('Import completed: User %d imported %d translations, updated %d, skipped %d from %s',
                    $currentUser->id, $results['imported'], $results['updated'], $results['skipped'], $uploadedFile->name),
                'translation-manager'
            );
            
            $response = [
                'success' => true,
                'imported' => $results['imported'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors']
            ];
            
            if ($includeDetails && isset($results['details'])) {
                $response['details'] = $results['details'];
            }
            
            return $this->asJson($response);
            
        } catch (\Exception $e) {
            Craft::warning('CSV import failed: ' . $e->getMessage(), 'translation-manager');
            Craft::warning('Import failure details: ' . $e->getTraceAsString(), 'translation-manager');
            
            return $this->asJson([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Process CSV file
     */
    private function processCsv(string $filePath, bool $includeDetails = false): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Could not open file');
        }
        
        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);
        
        // Read headers
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new \Exception('Could not read CSV headers');
        }
        
        // Clean headers (remove BOM, trim whitespace)
        $headers = array_map(function($header) {
            return trim(str_replace("\xEF\xBB\xBF", '', $header));
        }, $headers);
        
        // Find column indexes
        $keyIndex = $this->findColumnIndex($headers, ['Translation Key', 'English Text', 'English', 'En', 'EN', 'en', 'Source', 'Original']);
        $translationIndex = $this->findColumnIndex($headers, ['Translation', 'Arabic Translation', 'Arabic', 'Ar', 'AR', 'ar', 'Translated']);
        $siteIdIndex = $this->findColumnIndex($headers, ['Site ID', 'SiteID', 'Site_ID']);
        $siteLanguageIndex = $this->findColumnIndex($headers, ['Site Language', 'Site_Language', 'Language', 'Site']);
        $contextIndex = $this->findColumnIndex($headers, ['Context', 'Category']);
        $statusIndex = $this->findColumnIndex($headers, ['Status']);
        
        
        if ($keyIndex === false) {
            fclose($handle);
            throw new \Exception('Could not find Translation Key column');
        }
        
        // Process rows
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;
        
        // Detailed results
        $details = $includeDetails ? [
            'imported' => [],
            'updated' => []
        ] : null;
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            
            // Debug first few rows to see what fgetcsv returns
            if ($rowNumber <= 3 && isset($row[$keyIndex])) {
                Craft::debug("CSV DEBUG: Row {$rowNumber} raw: '" . $row[$keyIndex] . "' (len:" . strlen($row[$keyIndex]) . ")", 'translation-manager');
            }
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            try {
                // Extract data - preserve exact CSV values including all spacing
                $keyText = isset($row[$keyIndex]) ? $row[$keyIndex] : '';
                $translationText = ($translationIndex !== false && isset($row[$translationIndex])) ? $row[$translationIndex] : '';
                $context = ($contextIndex !== false && isset($row[$contextIndex])) ? $row[$contextIndex] : 'site';
                
                // Only skip truly empty keys
                if ($keyText === '' || $keyText === null) {
                    $skipped++;
                    continue;
                }
                
                // Extract site information
                $siteId = null;
                if ($siteIdIndex !== false && isset($row[$siteIdIndex])) {
                    $siteId = (int) trim($row[$siteIdIndex]);
                } elseif ($siteLanguageIndex !== false && isset($row[$siteLanguageIndex])) {
                    // Try to find site by language if Site ID not provided
                    $siteLanguage = trim($row[$siteLanguageIndex]);
                    $site = $this->findSiteByLanguage($siteLanguage);
                    $siteId = $site ? $site->id : null;
                }
                
                // Default to first site if no site info provided
                if (!$siteId) {
                    $siteId = Craft::$app->getSites()->getPrimarySite()->id;
                }
                
                
                // Validate required fields
                if (empty($keyText)) {
                    $skipped++;
                    continue;
                }
                
                // Minimal sanitization - preserve original formatting for re-import compatibility
                // Only remove truly dangerous executable content, preserve HTML formatting
                $dangerousPatterns = [
                    '/<script[^>]*>.*?<\/script>/si',
                    '/javascript:/i',
                    '/on\w+\s*=/i',
                    '/<iframe[^>]*>.*?<\/iframe>/si',
                    '/<object[^>]*>.*?<\/object>/si',
                    '/<embed[^>]*>/i',
                    '/data:text\/html/i',
                    '/vbscript:/i'
                ];
                
                foreach ($dangerousPatterns as $pattern) {
                    $keyText = preg_replace($pattern, '', $keyText);
                    $translationText = preg_replace($pattern, '', $translationText);
                    $context = preg_replace($pattern, '', $context);
                }
                
                // Only sanitize context field (should be simple text)
                $context = StringHelper::stripHtml($context);
                
                // Default empty context to 'site'
                if (empty($context)) {
                    $context = 'site';
                }
                
                // Use context exactly as provided in CSV (don't normalize for re-import)
                // This preserves the exact context from the export
                
                // Validate length
                if (strlen($keyText) > 5000 || strlen($translationText) > 5000) {
                    $errors[] = "Row $rowNumber: Text too long (max 5000 characters)";
                    Craft::warning("Import validation failed on row $rowNumber: Text too long (key: " . strlen($keyText) . " chars, translation: " . strlen($translationText) . " chars)", 'translation-manager');
                    continue;
                }
                
                // Status is always auto-determined based on content
                // Ignore any status column in the CSV
                
                // Use the service method which handles deduplication
                $translationService = TranslationManager::getInstance()->translations;
                $sourceHash = md5($keyText);
                
                // Check if translation exists for this specific site (match unique constraint)
                $existingTranslation = TranslationRecord::findOne([
                    'sourceHash' => $sourceHash,
                    'siteId' => $siteId
                ]);
                
                if ($existingTranslation) {
                    // Check if there's actually a change (handle NULL vs empty string)
                    $dbNormalized = $existingTranslation->translation === null ? '' : $existingTranslation->translation;
                    $csvNormalized = $translationText === null ? '' : $translationText;
                    
                    if ($dbNormalized === $csvNormalized) {
                        // No change, skip
                        $skipped++;
                        continue;
                    }
                    
                    // Update existing
                    $existingTranslation->translation = $translationText;
                    
                    // Auto-determine status based on content (preserve 'unused' status)
                    if ($existingTranslation->status !== 'unused') {
                        $existingTranslation->status = $translationText ? 'translated' : 'pending';
                    }
                    
                    // Update context if the imported one is more specific
                    if (str_starts_with($context, 'formie.') && $existingTranslation->context !== $context) {
                        if (substr_count($context, '.') > substr_count($existingTranslation->context, '.')) {
                            $existingTranslation->context = $context;
                        }
                    }
                    
                    if ($translationService->saveTranslation($existingTranslation)) {
                        $updated++;
                        
                        // Add to details if requested
                        if ($includeDetails && count($details['updated']) < 50) {
                            $details['updated'][] = [
                                'key' => $keyText,
                                'translation' => $translationText,
                                'context' => $context,
                                'previousTranslation' => $existingTranslation->getOldAttribute('translation')
                            ];
                        }
                    } else {
                        $validationErrors = $existingTranslation->getFirstErrors();
                        $errorMsg = !empty($validationErrors) ? implode(', ', $validationErrors) : 'Failed to update translation';
                        $errors[] = "Row $rowNumber: $errorMsg (Key: " . substr($keyText, 0, 50) . "...)";
                    }
                } else {
                    // Find or create translation for this specific site
                    try {
                        // Look for existing translation for this site (match unique constraint)
                        $translation = TranslationRecord::findOne([
                            'sourceHash' => md5($keyText),
                            'siteId' => $siteId
                        ]);
                        
                        $isNew = false;
                        if (!$translation) {
                            // Create new translation for this site
                            $translation = new TranslationRecord();
                            $translation->source = $keyText; // ← MISSING FIELD
                            $translation->sourceHash = md5($keyText);
                            $translation->siteId = $siteId;
                            $translation->context = $context;
                            $translation->translationKey = $keyText;
                            $translation->usageCount = 1;
                            $translation->lastUsed = new \DateTime();
                            $translation->dateCreated = new \DateTime();
                            $isNew = true;
                        }
                        
                        // Update translation and status (ensure source is set)
                        if (!$translation->source) {
                            $translation->source = $keyText; // Fix missing source field
                        }
                        $translation->translation = $translationText;
                        $translation->status = $translationText ? 'translated' : 'pending';
                        $translation->dateUpdated = new \DateTime();
                        
                        if ($translation->save()) {
                            if ($isNew) {
                                $imported++;
                                Craft::info("Import: Created new translation '{$keyText}' for site {$siteId}", 'translation-manager');
                            } else {
                                $updated++;
                                Craft::info("Import: Updated translation '{$keyText}' for site {$siteId}", 'translation-manager');
                            }
                            
                            // Add to details if requested
                            if ($includeDetails && count($details[$isNew ? 'imported' : 'updated']) < 50) {
                                $details[$isNew ? 'imported' : 'updated'][] = [
                                    'key' => $keyText,
                                    'translation' => $translationText,
                                    'context' => $context,
                                    'siteId' => $siteId
                                ];
                            }
                        } else {
                            $validationErrors = $translation->getFirstErrors();
                            $errorMsg = !empty($validationErrors) ? implode(', ', $validationErrors) : 'Failed to save translation';
                            $errors[] = "Row $rowNumber: $errorMsg (Key: " . substr($keyText, 0, 50) . "...)";
                            Craft::warning("Import: Failed to save '{$keyText}' for site {$siteId}: {$errorMsg}", 'translation-manager');
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Row $rowNumber: " . $e->getMessage();
                    }
                }
                
            } catch (\Exception $e) {
                $errors[] = "Row $rowNumber: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        $result = [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10) // Limit errors to prevent huge responses
        ];
        
        if ($includeDetails) {
            $result['details'] = $details;
        }
        
        return $result;
    }
    
    /**
     * Detect CSV delimiter
     */
    private function detectDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];
        
        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }
        
        return array_search(max($counts), $counts) ?: ',';
    }
    
    /**
     * Find column index by possible names
     */
    private function findColumnIndex(array $headers, array $possibleNames): int|false
    {
        foreach ($possibleNames as $name) {
            $index = array_search($name, $headers);
            if ($index !== false) {
                return $index;
            }
            
            // Case-insensitive search
            foreach ($headers as $i => $header) {
                if (strcasecmp($header, $name) === 0) {
                    return $i;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Import history page
     */
    public function actionHistory(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || !Craft::$app->getUser()->checkPermission('translationManager:viewTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to view import history.');
        }
        
        // Get all import history records
        $history = ImportHistoryRecord::find()
            ->with('user')
            ->orderBy(['dateCreated' => SORT_DESC])
            ->all();
        
        // Format the data for display
        $formattedHistory = [];
        foreach ($history as $record) {
            $formattedHistory[] = [
                'id' => $record->id,
                'filename' => $record->filename,
                'filesize' => Craft::$app->getFormatter()->asShortSize($record->filesize),
                'imported' => $record->imported,
                'updated' => $record->updated,
                'skipped' => $record->skipped,
                'errors' => $record->errors ? json_decode($record->errors, true) : [],
                'hasErrors' => !empty($record->errors),
                'backupPath' => $record->backupPath,
                'user' => $record->user ? $record->user->username : 'Unknown',
                'dateCreated' => $record->dateCreated,
                'formattedDate' => Craft::$app->getFormatter()->asDatetime($record->dateCreated, 'short'),
            ];
        }
        
        return $this->renderTemplate('translation-manager/import/history', [
            'history' => $formattedHistory,
        ]);
    }
    
    /**
     * Log malicious content attempts (for audit trail)
     */
    public function actionLogMalicious(): Response
    {
        // Require permission to import translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }
        
        $maliciousRows = Craft::$app->getRequest()->getBodyParam('maliciousRows', []);
        
        foreach ($maliciousRows as $row) {
            $english = $row['english'] ?? '';
            $threats = $row['threats'] ?? [];
            $threatList = [];
            
            foreach ($threats as $field => $fieldThreats) {
                $threatList[] = $field . ': ' . implode(', ', $fieldThreats);
            }
            
            Craft::warning("Malicious content blocked in import - '{$english}': " . implode('; ', $threatList), 'translation-manager');
        }
        
        return $this->asJson(['success' => true]);
    }
    
    /**
     * Check if content contains malicious patterns
     */
    private function containsMaliciousContent(string $content, &$threats = []): bool
    {
        $threats = [];
        
        // Check for dangerous patterns (but NOT safe HTML tags like <p>, <br>, etc.)
        $dangerousPatterns = [
            '/<script[^>]*>/i' => 'Script tag',
            '/<svg[^>]*>/i' => 'SVG tag',
            '/<iframe[^>]*>/i' => 'Iframe tag',
            '/<object[^>]*>/i' => 'Object tag',
            '/<embed[^>]*>/i' => 'Embed tag',
            '/<form[^>]*>/i' => 'Form tag',
            '/javascript:/i' => 'JavaScript protocol',
            '/vbscript:/i' => 'VBScript protocol',
            '/data:text\/html/i' => 'Data URL',
            '/on\w+\s*=/i' => 'Event handler',
            '/<meta[^>]*http-equiv/i' => 'Meta refresh',
            '/<base[^>]*href/i' => 'Base tag'
        ];
        
        // Check for formula injection patterns but exclude phone numbers
        // Phone numbers like '+9665XXXXXXXX' should not be flagged
        if (!preg_match('/^\+?\d+[X\d]*$/', trim($content))) {
            // Only check for formula injection if it's not a phone number pattern
            if (preg_match('/^[=\-@\|]/', trim($content))) {
                // Exclude + from formula injection since it's used in phone numbers
                $threats[] = 'Formula injection';
            }
        }
        
        foreach ($dangerousPatterns as $pattern => $threat) {
            if (preg_match($pattern, $content)) {
                $threats[] = $threat;
            }
        }
        
        // Check for encoded entities that might be dangerous
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($decoded !== $content) {
            // Re-check decoded content
            foreach ($dangerousPatterns as $pattern => $threat) {
                if (preg_match($pattern, $decoded)) {
                    $threats[] = $threat . ' (encoded)';
                }
            }
        }
        
        return !empty($threats);
    }
    
    /**
     * Clear import logs
     */
    public function actionClearLogs(): Response
    {
        $this->requirePostRequest();
        
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || !Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'error' => 'User is not authorized to clear import logs.']);
            }
            throw new ForbiddenHttpException('User is not authorized to clear import logs.');
        }
        
        try {
            // Delete all import history records
            ImportHistoryRecord::deleteAll();
            
            // Log the action
            Craft::info(
                sprintf('User %d cleared all import logs', $currentUser->id),
                'translation-manager'
            );
            
            if ($this->request->getAcceptsJson()) {
                // Set the notice for when the page reloads
                Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Import logs cleared successfully.'));
                
                return $this->asJson([
                    'success' => true
                ]);
            }
            
            Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Import logs cleared successfully.'));
            return $this->redirect('translation-manager/settings/import-export#history');
            
        } catch (\Exception $e) {
            Craft::error('Failed to clear import logs: ' . $e->getMessage(), 'translation-manager');
            
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'error' => 'Failed to clear import logs.']);
            }
            
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to clear import logs.'));
            return $this->redirect('translation-manager/settings/import-export#history');
        }
    }
    
    /**
     * Find site by language code (helper for multi-site import)
     */
    private function findSiteByLanguage(string $language): ?\craft\models\Site
    {
        $sites = Craft::$app->getSites()->getAllSites();
        
        foreach ($sites as $site) {
            // Exact match
            if ($site->language === $language) {
                return $site;
            }
            // Case insensitive match  
            if (strcasecmp($site->language, $language) === 0) {
                return $site;
            }
        }
        
        return null;
    }
    
    /**
     * Get site language by ID (helper for server response)
     */
    private function getSiteLanguageById(int $siteId): string
    {
        $site = Craft::$app->getSites()->getSiteById($siteId);
        return $site ? $site->language : 'unknown';
    }
}