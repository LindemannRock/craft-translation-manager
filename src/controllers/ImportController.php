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
use lindemannrock\base\helpers\CsvImportHelper;
use lindemannrock\base\helpers\DateFormatHelper;
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
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionCheckExisting(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException('User is not authorized to check translations.');
        }
        
        $translations = Craft::$app->getRequest()->getBodyParam('translations', []);
        
        if (!is_array($translations)) {
            return $this->asJson([
                'success' => false,
                'error' => 'Invalid data format',
            ]);
        }
        
        $analysis = $this->analyzeTranslations($translations);

        return $this->asJson([
            'success' => true,
            'toImport' => $analysis['toImport'],
            'toUpdate' => $analysis['toUpdate'],
            'unchanged' => $analysis['unchanged'],
            'malicious' => $analysis['malicious'],
            'errors' => $analysis['errors'],
        ]);
    }

    /**
     * Upload and parse CSV file for mapping.
     *
     * @return Response
     * @since 5.21.0
     */
    public function actionUpload(): Response
    {
        $this->requirePostRequest();

        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }

        $uploadedFile = UploadedFile::getInstanceByName('csvFile');

        if (!$uploadedFile) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Please select a CSV file to upload.'));
            return $this->redirect('translation-manager/import-export');
        }

        $delimiter = Craft::$app->getRequest()->getBodyParam('delimiter', 'auto');
        $detectDelimiter = true;
        if ($delimiter !== 'auto') {
            if ($delimiter === "\t") {
                $delimiter = "\t";
            }
            $detectDelimiter = false;
        } else {
            $delimiter = null;
        }

        try {
            $parsed = CsvImportHelper::parseUpload($uploadedFile, [
                'maxRows' => CsvImportHelper::DEFAULT_MAX_ROWS,
                'maxBytes' => CsvImportHelper::DEFAULT_MAX_BYTES,
                'delimiter' => $delimiter,
                'detectDelimiter' => $detectDelimiter,
            ]);

            $settings = TranslationManager::getInstance()->getSettings();
            $defaultCreateBackup = $settings->backupEnabled && $settings->backupOnImport;
            $createBackup = (bool)Craft::$app->getRequest()->getBodyParam('createBackup', $defaultCreateBackup);

            if (!$settings->backupEnabled || !$settings->backupOnImport) {
                $createBackup = false;
            }

            Craft::$app->getSession()->set('translation-import', [
                'headers' => $parsed['headers'],
                'allRows' => $parsed['allRows'],
                'rowCount' => $parsed['rowCount'],
                'delimiter' => $parsed['delimiter'],
                'filename' => $uploadedFile->name,
                'filesize' => $uploadedFile->size,
                'createBackup' => $createBackup,
            ]);

            return $this->redirect('translation-manager/import/map');
        } catch (\Exception $e) {
            $this->logError('Failed to parse CSV', ['error' => $e->getMessage()]);
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to parse CSV: {error}', ['error' => $e->getMessage()]));
            return $this->redirect('translation-manager/import-export');
        }
    }

    /**
     * Map CSV columns to translation fields.
     *
     * @return Response
     * @since 5.21.0
     */
    public function actionMap(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }

        $importData = Craft::$app->getSession()->get('translation-import');

        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'No import data found. Please upload a CSV file.'));
            return $this->redirect('translation-manager/import-export');
        }

        $previewRows = array_slice($importData['allRows'], 0, 5);

        return $this->renderTemplate('translation-manager/import-export/map', [
            'headers' => $importData['headers'],
            'previewRows' => $previewRows,
            'rowCount' => $importData['rowCount'],
            'createBackup' => $importData['createBackup'] ?? false,
        ]);
    }

    /**
     * Preview import results using mapped columns.
     *
     * @return Response
     * @since 5.21.0
     */
    public function actionPreview(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }

        if (!Craft::$app->getRequest()->getIsPost()) {
            $previewData = Craft::$app->getSession()->get('translation-preview');

            if (!$previewData) {
                Craft::$app->getSession()->setError(Craft::t('translation-manager', 'No preview data found. Please map columns first.'));
                return $this->redirect('translation-manager/import-export');
            }

            return $this->renderTemplate('translation-manager/import-export/preview', $previewData);
        }

        $importData = Craft::$app->getSession()->get('translation-import');

        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Import session expired. Please upload the file again.'));
            return $this->redirect('translation-manager/import-export');
        }

        $mapping = Craft::$app->getRequest()->getBodyParam('mapping', []);
        $columnMap = [];
        foreach ($mapping as $colIndex => $fieldName) {
            if (!empty($fieldName)) {
                $columnMap[(int)$colIndex] = $fieldName;
            }
        }

        $mappedFields = array_values($columnMap);
        if (!in_array('translationKey', $mappedFields, true)) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Translation Key must be mapped.'));
            return $this->redirect('translation-manager/import/map');
        }

        $nonEmptyMappings = array_filter($mappedFields, fn($value) => $value !== '');
        if (count($nonEmptyMappings) !== count(array_unique($nonEmptyMappings))) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'You cannot map multiple CSV columns to the same field.'));
            return $this->redirect('translation-manager/import/map');
        }

        $translations = $this->buildTranslationsFromRows($importData['allRows'], $columnMap);
        $analysis = $this->analyzeTranslations($translations);

        $summary = [
            'totalRows' => count($translations),
            'toImport' => count($analysis['toImport']),
            'toUpdate' => count($analysis['toUpdate']),
            'unchanged' => count($analysis['unchanged']),
            'malicious' => count($analysis['malicious']),
            'errors' => count($analysis['errors']),
        ];

        Craft::$app->getSession()->set('translation-preview', [
            'summary' => $summary,
            'toImport' => $analysis['toImport'],
            'toUpdate' => $analysis['toUpdate'],
            'unchanged' => $analysis['unchanged'],
            'malicious' => $analysis['malicious'],
            'errors' => $analysis['errors'],
            'createBackup' => $importData['createBackup'] ?? false,
        ]);

        $importData['columnMap'] = $columnMap;
        Craft::$app->getSession()->set('translation-import', $importData);

        return $this->redirect('translation-manager/import/preview');
    }
    
    /**
     * Import mapped translations from the preview flow.
     *
     * @return Response
     * @since 5.21.0
     */
    public function actionIndex(): Response
    {
        $this->requirePostRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException('User is not authorized to import translations.');
        }
        
        $importData = Craft::$app->getSession()->get('translation-import');
        $previewData = Craft::$app->getSession()->get('translation-preview');

        if (!$importData || !isset($importData['allRows'])) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Import session expired. Please upload the file again.'));
            return $this->redirect('translation-manager/import-export');
        }

        if (!$previewData) {
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'No preview data found. Please preview your import first.'));
            return $this->redirect('translation-manager/import-export');
        }

        $translations = array_merge($previewData['toImport'] ?? [], $previewData['toUpdate'] ?? []);
        if (empty($translations)) {
            Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'No valid translations found to import.'));
            return $this->redirect('translation-manager/import-export');
        }

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $backupPath = null;
            $createBackup = (bool)($importData['createBackup'] ?? false);
            if ($settings->backupEnabled && $settings->backupOnImport && $createBackup) {
                $backupPath = TranslationManager::getInstance()->backup->createBackup('before_import');
            }

            $tempCsvPath = $this->writeTranslationsCsv($translations);
            $results = $this->processCsv($tempCsvPath, false);
            @unlink($tempCsvPath);

            if ($settings->autoExport) {
                TranslationManager::getInstance()->export->exportAll();
            }
            
            // Save import history
            $history = new ImportHistoryRecord();
            $history->userId = $currentUser->id;
            $history->filename = $importData['filename'] ?? null;
            $history->filesize = $importData['filesize'] ?? null;
            $history->imported = $results['imported'];
            $history->updated = $results['updated'];
            $history->skipped = $results['skipped'];
            $history->errors = !empty($results['errors']) ? json_encode($results['errors']) : null;
            $history->backupPath = $backupPath ? basename($backupPath) : null;
            $history->save();
            
            // Log the import results
            $this->logInfo('Import completed', [
                'userId' => $currentUser->id,
                'imported' => $results['imported'],
                'updated' => $results['updated'],
                'skipped' => $results['skipped'],
                'filename' => $importData['filename'] ?? null,
            ]);

            Craft::$app->getSession()->remove('translation-import');
            Craft::$app->getSession()->remove('translation-preview');

            $message = Craft::t('translation-manager', 'Successfully imported {imported} translations.', [
                'imported' => $results['imported'],
            ]);
            if ($results['updated'] > 0) {
                $message .= ' ' . Craft::t('translation-manager', '{updated} updated.', [
                    'updated' => $results['updated'],
                ]);
            }
            if ($results['skipped'] > 0) {
                $message .= ' ' . Craft::t('translation-manager', '{skipped} skipped.', [
                    'skipped' => $results['skipped'],
                ]);
            }

            Craft::$app->getSession()->setNotice($message);
            return $this->redirect('translation-manager/import-export');
        } catch (\Exception $e) {
            $this->logError('CSV import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Import failed: {error}', [
                'error' => $e->getMessage(),
            ]));
            return $this->redirect('translation-manager/import-export');
        }
    }

    /**
     * Build translation rows from CSV data using the selected column mapping.
     *
     * @param array $rows
     * @param array $columnMap
     * @return array
     * @since 5.21.0
     */
    private function buildTranslationsFromRows(array $rows, array $columnMap): array
    {
        $translations = [];
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;

            if (empty(array_filter($row))) {
                continue;
            }

            $translation = [
                'english' => '',
                'arabic' => '',
                'context' => '',
                'category' => '',
                'siteId' => '',
                'siteLanguage' => '',
                'type' => '',
                'status' => '',
                '_rowNumber' => $rowNumber,
            ];

            foreach ($columnMap as $colIndex => $fieldName) {
                if (!array_key_exists($colIndex, $row)) {
                    continue;
                }

                $value = (string)$row[$colIndex];

                switch ($fieldName) {
                    case 'translationKey':
                        $translation['english'] = $value;
                        break;
                    case 'translation':
                        $translation['arabic'] = $value;
                        break;
                    case 'language':
                        $translation['siteLanguage'] = $value;
                        break;
                    case 'siteId':
                        $translation['siteId'] = $value;
                        break;
                    case 'context':
                        $translation['context'] = $value;
                        break;
                    case 'category':
                        $translation['category'] = $value;
                        break;
                    case 'type':
                        $translation['type'] = $value;
                        break;
                    case 'status':
                        $translation['status'] = $value;
                        break;
                }
            }

            $translations[] = $translation;
        }

        return $translations;
    }

    /**
     * Write mapped translations to a temporary CSV file for import.
     *
     * @param array $translations
     * @return string
     * @since 5.21.0
     */
    private function writeTranslationsCsv(array $translations): string
    {
        $tempPath = Craft::$app->getPath()->getTempPath() . '/translation-import-' . uniqid() . '.csv';
        $handle = fopen($tempPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException('Failed to create temporary CSV file.');
        }

        $headers = [
            'Translation Key',
            'Translation',
            'Language',
            'Category',
            'Context',
            'Type',
            'Site ID',
        ];

        fputcsv($handle, $headers);

        foreach ($translations as $translation) {
            $key = $translation['english'] ?? '';
            if ($key === '') {
                continue;
            }

            $language = $translation['siteLanguage'] ?? $translation['language'] ?? '';

            fputcsv($handle, [
                $key,
                $translation['arabic'] ?? '',
                $language,
                $translation['category'] ?? '',
                $translation['context'] ?? '',
                $translation['type'] ?? '',
                $translation['siteId'] ?? '',
            ]);
        }

        fclose($handle);

        return $tempPath;
    }

    /**
     * Analyze translations for preview and existing checks.
     *
     * @param array $translations
     * @return array
     * @since 5.21.0
     */
    private function analyzeTranslations(array $translations): array
    {
        $toImport = [];
        $toUpdate = [];
        $unchanged = [];
        $maliciousRows = [];
        $errors = [];

        foreach ($translations as $translation) {
            if (!isset($translation['english']) || $translation['english'] === '') {
                $errors[] = [
                    'rowNumber' => $translation['_rowNumber'] ?? null,
                    'english' => '',
                    'arabic' => $translation['arabic'] ?? '',
                    'context' => $translation['context'] ?? 'site',
                    'error' => 'Missing Translation Key',
                ];
                continue;
            }

            $targetLanguage = null;
            if (!empty($translation['siteLanguage'])) {
                $targetLanguage = $translation['siteLanguage'];
            } elseif (!empty($translation['siteId'])) {
                $siteId = (int)$translation['siteId'];
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if ($site) {
                    $targetLanguage = $site->language;
                } else {
                    $errors[] = [
                        'rowNumber' => $translation['_rowNumber'] ?? null,
                        'english' => $translation['english'],
                        'arabic' => $translation['arabic'] ?? '',
                        'context' => $translation['context'] ?? 'site',
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

            if (!$targetLanguage) {
                $targetLanguage = Craft::$app->getSites()->getPrimarySite()->language;
            }

            $originalEnglish = $translation['english'];
            $originalTranslation = $translation['arabic'] ?? '';
            $originalContext = $translation['context'] ?? 'site';

            $isMalicious = false;
            $detectedThreats = [];
            $fieldsToCheck = [
                'English' => $originalEnglish,
                'Translation' => $originalTranslation,
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
                    'rowNumber' => $translation['_rowNumber'] ?? null,
                    'english' => $originalEnglish,
                    'arabic' => $originalTranslation,
                    'context' => $originalContext,
                    'threats' => $detectedThreats,
                ];
                $this->logWarning("Malicious content detected in translation", [
                    'english' => $originalEnglish,
                    'threats' => array_keys($detectedThreats),
                ]);
                continue;
            }

            $keyText = $translation['english'];
            $translationText = $translation['arabic'] ?? '';
            $context = isset($translation['context']) ? StringHelper::stripHtml($translation['context']) : 'site';

            $keyText = $this->stripFormulaEscapePrefix($keyText);
            $translationText = $this->stripFormulaEscapePrefix($translationText);
            $context = $this->stripFormulaEscapePrefix($context);

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

            if (empty($context)) {
                $context = 'site';
            }

            $category = isset($translation['category']) ? StringHelper::stripHtml($translation['category']) : '';
            $category = $this->stripFormulaEscapePrefix($category);
            $type = isset($translation['type']) ? strtolower(trim($translation['type'])) : '';

            if (empty($category)) {
                $category = 'messages';
            }

            if ($type === 'forms' || $context === 'formie' || str_starts_with($context, 'formie.')) {
                $category = 'formie';
            }

            $sourceHash = md5($keyText);
            $existing = TranslationRecord::findOne([
                'sourceHash' => $sourceHash,
                'language' => $targetLanguage,
                'category' => $category,
            ]);

            if ($existing) {
                $dbValue = $existing->translation;
                $csvValue = $translationText;

                $dbNormalized = $dbValue === null ? '' : $dbValue;
                $csvNormalized = $csvValue === null ? '' : $csvValue;

                if ($dbNormalized === $csvNormalized) {
                    $unchanged[] = [
                        'rowNumber' => $translation['_rowNumber'] ?? null,
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
                        'rowNumber' => $translation['_rowNumber'] ?? null,
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
                    'rowNumber' => $translation['_rowNumber'] ?? null,
                    'english' => $keyText,
                    'arabic' => $translationText,
                    'context' => $context,
                    'category' => $category,
                    'language' => $targetLanguage,
                ];
            }
        }

        return [
            'toImport' => $toImport,
            'toUpdate' => $toUpdate,
            'unchanged' => $unchanged,
            'malicious' => $maliciousRows,
            'errors' => $errors,
        ];
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

                // Strip CSV formula-escape prefix (apostrophe followed by formula character)
                // This restores original values that were escaped during export
                $keyText = $this->stripFormulaEscapePrefix($keyText);
                $translationText = $this->stripFormulaEscapePrefix($translationText);
                $context = $this->stripFormulaEscapePrefix($context);
                $category = $this->stripFormulaEscapePrefix($category);

                // Check for malicious content (same checks as preview)
                $threats = [];
                if ($this->containsMaliciousContent($keyText, $threats)
                    || $this->containsMaliciousContent($translationText, $threats)
                    || $this->containsMaliciousContent($context, $threats)) {
                    $errors[] = Craft::t('translation-manager', 'Row {row}: Malicious content blocked ({threats})', [
                        'row' => $rowNumber,
                        'threats' => implode(', ', $threats),
                    ]);
                    $skipped++;
                    continue;
                }

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
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionHistory(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:viewImportHistory'))) {
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
                'formattedDate' => DateFormatHelper::formatDatetime($record->dateCreated),
            ];
        }
        
        return $this->renderTemplate('translation-manager/import/history', [
            'history' => $formattedHistory,
        ]);
    }
    
    /**
     * Log malicious content attempts (for audit trail)
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionLogMalicious(): Response
    {
        // Require permission to import translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
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
     *
     * @return Response
     * @since 1.0.0
     */
    public function actionClearLogs(): Response
    {
        $this->requirePostRequest();
        
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport') &&
            !Craft::$app->getUser()->checkPermission('translationManager:clearImportHistory'))) {
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

    /**
     * Strip CSV formula-escape prefix from imported values
     *
     * During export, values starting with =, +, -, @ (including with leading whitespace)
     * are prefixed with apostrophe to prevent formula injection in spreadsheets.
     * This method restores the original value by removing only the leading apostrophe
     * when followed by optional whitespace and a formula character.
     *
     * Examples:
     *   "'=SUM(A1)" -> "=SUM(A1)"
     *   "'+1234" -> "+1234"
     *   "'  =1" -> "  =1" (preserves internal whitespace)
     *   "'test" -> "'test" (no change - 't' is not a formula char)
     */
    private function stripFormulaEscapePrefix(string $value): string
    {
        // Only strip apostrophe if followed by optional whitespace and formula character
        // This preserves legitimate values like 'test' or 'Hello
        if (preg_match("/^'(\\s*[=+\\-@])/", $value)) {
            return substr($value, 1);
        }

        return $value;
    }
}
