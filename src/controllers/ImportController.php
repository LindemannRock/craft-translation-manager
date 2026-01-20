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
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\UploadedFile;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * CSV Import Controller
 *
 * @since 1.0.0
 */
class ImportController extends Controller
{
    use LoggingTrait;
    /**
     * @var array<int|string>|bool|int
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
        if (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to check translations.');
        }
        
        $translations = Craft::$app->getRequest()->getBodyParam('translations', []);
        
        if (!is_array($translations)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid data format',
            ]);
        }
        
        
        $toImport = [];
        $toUpdate = [];
        $unchanged = [];
        $maliciousRows = [];
        $errors = [];
        
        foreach ($translations as $translation) {
            if (!isset($translation['english']) || empty($translation['english'])) {
                $this->logWarning("Skipping translation with empty or missing English text");
                continue;
            }
            
            // Extract language from CSV data (preferred) or fall back to siteId
            $targetLanguage = null;
            if (isset($translation['siteLanguage']) && !empty($translation['siteLanguage'])) {
                // Direct language from CSV
                $targetLanguage = $translation['siteLanguage'];
            } elseif (isset($translation['siteId']) && !empty($translation['siteId'])) {
                // Legacy: get language from siteId
                $siteId = (int) $translation['siteId'];
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if ($site) {
                    $targetLanguage = $site->language;
                } else {
                    $errors[] = [
                        'english' => $translation['english'],
                        'arabic' => isset($translation['arabic']) ? $translation['arabic'] : '',
                        'context' => isset($translation['context']) ? $translation['context'] : 'site',
                        'siteId' => $siteId,
                        'siteLanguage' => 'unknown',
                        'error' => "Invalid site ID: {$siteId} does not exist",
                    ];
                    $this->logWarning("Invalid site ID for translation", [
                        'siteId' => $siteId,
                        'english' => $translation['english'],
                    ]);
                    continue;
                }
            }

            // Default to primary site's language if no language info provided
            if (!$targetLanguage) {
                $targetLanguage = Craft::$app->getSites()->getPrimarySite()->language;
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
                'Context' => $originalContext,
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
                    'threats' => $detectedThreats,
                ];
                $this->logWarning("Malicious content detected in translation", [
                    'english' => $originalEnglish,
                    'threats' => array_keys($detectedThreats),
                ]);
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
                '/vbscript:/i',
            ];
            
            foreach ($dangerousPatterns as $pattern) {
                $keyText = preg_replace($pattern, '', $keyText);
                $translationText = preg_replace($pattern, '', $translationText);
            }
            
            // Use context exactly as provided in CSV (don't normalize for re-import)
            if (empty($context)) {
                $context = 'site';
            }

            // Extract category from CSV or determine from context/type
            $category = isset($translation['category']) ? StringHelper::stripHtml($translation['category']) : '';
            $type = isset($translation['type']) ? strtolower(trim($translation['type'])) : '';

            if (empty($category)) {
                $category = 'messages';
            }

            // Protection: if type is 'forms' or context indicates formie, category must be 'formie'
            if ($type === 'forms' || $context === 'formie' || str_starts_with($context, 'formie.')) {
                $category = 'formie';
            }

            // Find existing by hash - match unique constraint (sourceHash + language + category)
            $sourceHash = md5($keyText);
            $existing = TranslationRecord::findOne([
                'sourceHash' => $sourceHash,
                'language' => $targetLanguage,
                'category' => $category,
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
                        'category' => $category,
                        'currentTranslation' => $existing->translation,
                        'currentStatus' => $existing->status,
                        'existingContext' => $existing->context,
                        'language' => $targetLanguage,
                    ];
                } else {
                    $toUpdate[] = [
                        'english' => $keyText,
                        'arabic' => $translationText,
                        'currentTranslation' => $existing->translation ?? '',
                        'context' => $context,
                        'category' => $category,
                        'currentStatus' => $existing->status,
                        'existingContext' => $existing->context,
                        'language' => $targetLanguage,
                    ];
                }
            } else {
                $toImport[] = [
                    'english' => $keyText,
                    'arabic' => $translationText,
                    'context' => $context,
                    'category' => $category,
                    'language' => $targetLanguage,
                ];
            }
        }
        
        return $this->asJson([
            'success' => true,
            'toImport' => $toImport,
            'toUpdate' => $toUpdate,
            'unchanged' => $unchanged,
            'malicious' => $maliciousRows,
            'errors' => $errors,
        ]);
    }
    
    /**
     * Import CSV file
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        
        if (!$currentUser || !Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }
        
        $uploadedFile = UploadedFile::getInstanceByName('csvFile');
        
        // Validate file upload
        if (!$uploadedFile) {
            $this->logWarning('Import failed: No file uploaded');
            return $this->asJson([
                'success' => false,
                'error' => 'No file uploaded',
            ]);
        }

        $this->logInfo("Import started", [
            'filename' => $uploadedFile->name,
            'size' => $uploadedFile->size,
        ]);
        
        // Validate file type
        $extension = strtolower($uploadedFile->getExtension());
        if (!in_array($extension, ['csv', 'txt'])) {
            return $this->asJson([
                'success' => false,
                'error' => 'Only CSV files are allowed',
            ]);
        }
        
        // Validate file size (5MB limit)
        if ($uploadedFile->size > 5242880) {
            return $this->asJson([
                'success' => false,
                'error' => 'File size exceeds 5MB limit',
            ]);
        }
        
        // Validate MIME type
        $mimeType = $uploadedFile->getMimeType();
        $allowedMimeTypes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid file type',
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
            $this->logInfo('Import completed', [
                'userId' => $currentUser->id,
                'imported' => $results['imported'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'filename' => $uploadedFile->name,
            ]);
            
            $response = [
                'success' => true,
                'imported' => $results['imported'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'errors' => $results['errors'],
            ];
            
            if ($includeDetails && isset($results['details'])) {
                $response['details'] = $results['details'];
            }
            
            return $this->asJson($response);
        } catch (\Exception $e) {
            $this->logError('CSV import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->asJson([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
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
        $contextIndex = $this->findColumnIndex($headers, ['Context']);
        $categoryIndex = $this->findColumnIndex($headers, ['Category']);
        $typeIndex = $this->findColumnIndex($headers, ['Type']);
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
            'updated' => [],
        ] : null;
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            
            // Debug first few rows to see what fgetcsv returns
            if ($rowNumber <= 3 && isset($row[$keyIndex])) {
                $this->logDebug("CSV DEBUG: Row data", [
                    'rowNumber' => $rowNumber,
                    'raw' => $row[$keyIndex],
                    'length' => strlen($row[$keyIndex]),
                ]);
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
                $category = ($categoryIndex !== false && isset($row[$categoryIndex])) ? $row[$categoryIndex] : '';
                $type = ($typeIndex !== false && isset($row[$typeIndex])) ? strtolower(trim($row[$typeIndex])) : '';
                
                // Only skip truly empty keys
                if ($keyText === '') {
                    $skipped++;
                    continue;
                }
                
                // Extract language (preferred) or fall back to siteId
                $language = null;
                if ($siteLanguageIndex !== false && isset($row[$siteLanguageIndex])) {
                    $language = trim($row[$siteLanguageIndex]);
                } elseif ($siteIdIndex !== false && isset($row[$siteIdIndex])) {
                    // Legacy: get language from siteId
                    $siteId = (int) trim($row[$siteIdIndex]);
                    $site = Craft::$app->getSites()->getSiteById($siteId);
                    $language = $site ? $site->language : null;
                }

                // Default to primary site's language if no language info provided
                if (!$language) {
                    $language = Craft::$app->getSites()->getPrimarySite()->language;
                }

                // Get a siteId for backwards compatibility (used when creating new records)
                $siteId = $this->getSiteIdForLanguage($language);
                
                
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
                    '/vbscript:/i',
                ];
                
                foreach ($dangerousPatterns as $pattern) {
                    $keyText = preg_replace($pattern, '', $keyText);
                    $translationText = preg_replace($pattern, '', $translationText);
                    $context = preg_replace($pattern, '', $context);
                }
                
                // Only sanitize context and category fields (should be simple text)
                $context = StringHelper::stripHtml($context);
                $category = StringHelper::stripHtml($category);

                // Default empty context to 'site'
                if (empty($context)) {
                    $context = 'site';
                }

                // Default empty category to 'messages'
                if (empty($category)) {
                    $category = 'messages';
                }

                // Protection: if type is 'forms' or context indicates formie, category must be 'formie'
                if ($type === 'forms' || $context === 'formie' || str_starts_with($context, 'formie.')) {
                    $category = 'formie';
                }

                // Use context exactly as provided in CSV (don't normalize for re-import)
                // This preserves the exact context from the export
                
                // Validate length
                if (strlen($keyText) > 5000 || strlen($translationText) > 5000) {
                    $errors[] = "Row $rowNumber: Text too long (max 5000 characters)";
                    $this->logWarning("Import validation failed: Text too long", [
                        'rowNumber' => $rowNumber,
                        'keyLength' => strlen($keyText),
                        'translationLength' => strlen($translationText),
                    ]);
                    continue;
                }
                
                // Status is always auto-determined based on content
                // Ignore any status column in the CSV
                
                // Use the service method which handles deduplication
                $translationService = TranslationManager::getInstance()->translations;
                $sourceHash = md5($keyText);
                
                // Check if translation exists (match unique constraint: sourceHash + language + category)
                $existingTranslation = TranslationRecord::findOne([
                    'sourceHash' => $sourceHash,
                    'language' => $language,
                    'category' => $category,
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
                                'previousTranslation' => $existingTranslation->getOldAttribute('translation'),
                            ];
                        }
                    } else {
                        $validationErrors = $existingTranslation->getFirstErrors();
                        $errorMsg = !empty($validationErrors) ? implode(', ', $validationErrors) : 'Failed to update translation';
                        $errors[] = "Row $rowNumber: $errorMsg (Key: " . substr($keyText, 0, 50) . "...)";
                    }
                } else {
                    // Find or create translation for this language + category
                    try {
                        // Look for existing translation (match unique constraint)
                        $translation = TranslationRecord::findOne([
                            'sourceHash' => md5($keyText),
                            'language' => $language,
                            'category' => $category,
                        ]);
                        
                        $isNew = false;
                        if (!$translation) {
                            // Create new translation for this language + category
                            $translation = new TranslationRecord();
                            $translation->source = $keyText;
                            $translation->sourceHash = md5($keyText);
                            $translation->siteId = $siteId; // Backwards compatibility
                            $translation->language = $language;
                            $translation->context = $context;
                            $translation->category = $category;
                            $translation->translationKey = $keyText;
                            $translation->usageCount = 1;
                            $translation->lastUsed = Db::prepareDateForDb(new \DateTime());
                            $translation->dateCreated = Db::prepareDateForDb(new \DateTime());
                            $isNew = true;
                        }

                        // Update translation and status (ensure source is set)
                        if (!$translation->source) {
                            $translation->source = $keyText; // Fix missing source field
                        }
                        $translation->translation = $translationText;
                        $translation->status = $translationText ? 'translated' : 'pending';
                        // Update category if provided in CSV
                        if ($categoryIndex !== false) {
                            $translation->category = $category;
                        }
                        $translation->dateUpdated = Db::prepareDateForDb(new \DateTime());
                        
                        if ($translation->save()) {
                            if ($isNew) {
                                $imported++;
                                $this->logInfo("Import: Created new translation", [
                                    'key' => $keyText,
                                    'siteId' => $siteId,
                                ]);
                            } else {
                                $updated++;
                                $this->logInfo("Import: Updated translation", [
                                    'key' => $keyText,
                                    'siteId' => $siteId,
                                ]);
                            }
                            
                            // Add to details if requested
                            if ($includeDetails && count($details[$isNew ? 'imported' : 'updated']) < 50) {
                                $details[$isNew ? 'imported' : 'updated'][] = [
                                    'key' => $keyText,
                                    'translation' => $translationText,
                                    'context' => $context,
                                    'siteId' => $siteId,
                                ];
                            }
                        } else {
                            $validationErrors = $translation->getFirstErrors();
                            $errorMsg = !empty($validationErrors) ? implode(', ', $validationErrors) : 'Failed to save translation';
                            $errors[] = "Row $rowNumber: $errorMsg (Key: " . substr($keyText, 0, 50) . "...)";
                            $this->logWarning("Import: Failed to save translation", [
                                'key' => $keyText,
                                'siteId' => $siteId,
                                'error' => $errorMsg,
                            ]);
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
            'errors' => array_slice($errors, 0, 10), // Limit errors to prevent huge responses
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
        /** @var ImportHistoryRecord[] $history */
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
                'user' => $record->user->username ?? 'Unknown',
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
            
            $this->logWarning("Malicious content blocked in import", [
                'english' => $english,
                'threats' => $threatList,
            ]);
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
            '/<base[^>]*href/i' => 'Base tag',
        ];
        
        // Check for formula injection patterns but exclude safe patterns
        // Phone numbers like '+9665XXXXXXXX' should not be flagged
        // Text like '-- Select an option --' should not be flagged
        $trimmed = trim($content);
        if (!preg_match('/^\+?\d+[X\d]*$/', $trimmed)) {
            // Only check for formula injection if it's not a phone number pattern
            // = and @ are always dangerous at start
            // - is only dangerous if followed by digit or formula char (not -- or - text)
            // | is dangerous (pipe can be command separator)
            if (preg_match('/^[=@\|]/', $trimmed)) {
                $threats[] = 'Formula injection';
            } elseif (preg_match('/^-[0-9=@+\-\|]/', $trimmed) && !preg_match('/^--\s/', $trimmed)) {
                // Flag -1, -=, -@, etc. but not "-- text" (common placeholder)
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
        if (!$currentUser || !Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'error' => 'User is not authorized to clear import logs.']);
            }
            throw new ForbiddenHttpException('User is not authorized to clear import logs.');
        }
        
        try {
            // Delete all import history records
            ImportHistoryRecord::deleteAll();
            
            // Log the action
            $this->logInfo('User cleared all import logs', ['userId' => $currentUser->id]);
            
            if ($this->request->getAcceptsJson()) {
                // Set the notice for when the page reloads
                Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Import logs cleared successfully.'));
                
                return $this->asJson([
                    'success' => true,
                ]);
            }
            
            Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'Import logs cleared successfully.'));
            return $this->redirect('translation-manager/settings/import-export#history');
        } catch (\Exception $e) {
            $this->logError('Failed to clear import logs', ['error' => $e->getMessage()]);
            
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
     * Get a site ID for a given language (for backwards compatibility)
     */
    private function getSiteIdForLanguage(string $language): int
    {
        $site = $this->findSiteByLanguage($language);
        if ($site) {
            return $site->id;
        }

        // Fallback to primary site
        return Craft::$app->getSites()->getPrimarySite()->id;
    }
}
