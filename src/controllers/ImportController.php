<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for importing translations from CSV files
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
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
use lindemannrock\translationmanager\helpers\SiteLanguageHelper;
use lindemannrock\translationmanager\records\ImportHistoryRecord;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\IntegrationService;
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

    private const DANGEROUS_IMPORT_PATTERNS = [
        '/<script[^>]*>.*?<\/script>/si',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe[^>]*>.*?<\/iframe>/si',
        '/<object[^>]*>.*?<\/object>/si',
        '/<embed[^>]*>/i',
        '/data:text\/html/i',
        '/vbscript:/i',
    ];

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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User must be logged in.'));
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Check which translations already exist
     *
     * @return Response
     */
    public function actionCheckExisting(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to check translations.'));
        }
        
        $translations = Craft::$app->getRequest()->getBodyParam('translations', []);
        
        if (!is_array($translations)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid data format'),
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
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to import translations.'));
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
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to import translations.'));
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
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to import translations.'));
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
        
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations'))) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to import translations.'));
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
        $pluginName = TranslationManager::$plugin->getSettings()->getPluralLowerDisplayName();
        if (empty($translations)) {
            Craft::$app->getSession()->setNotice(Craft::t('translation-manager', 'No valid {pluginName} found to import.', [
                'pluginName' => $pluginName,
            ]));
            return $this->redirect('translation-manager/import-export');
        }

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            $backupPath = null;
            $createBackup = (bool)($importData['createBackup'] ?? false);
            if ($settings->backupEnabled && $settings->backupOnImport && $createBackup) {
                $backupPath = TranslationManager::getInstance()->backup->createBackup('before_import');
            }

            $results = $this->importTranslations($translations, false);

            TranslationManager::getInstance()->generate->triggerAutoGenerate();

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

            $message = Craft::t('translation-manager', 'Successfully imported {imported} {pluginName}.', [
                'imported' => $results['imported'],
                'pluginName' => $pluginName,
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
                'translationKey' => '',
                'translation' => '',
                'context' => '',
                'category' => '',
                'siteId' => '',
                'language' => '',
                'type' => '',
                'status' => '',
                'origin' => '',
                '_rowNumber' => $rowNumber,
            ];

            foreach ($columnMap as $colIndex => $fieldName) {
                if (!array_key_exists($colIndex, $row)) {
                    continue;
                }

                $value = (string)$row[$colIndex];

                switch ($fieldName) {
                    case 'translationKey':
                        $translation['translationKey'] = $value;
                        break;
                    case 'translation':
                        $translation['translation'] = $value;
                        break;
                    case 'language':
                        $translation['language'] = $value;
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
                    case 'origin':
                        $translation['origin'] = $value;
                        break;
                }
            }

            $translations[] = $translation;
        }

        return $translations;
    }

    /**
     * Analyze translations for preview and existing checks.
     *
     * @param array $translations
     * @return array
     */
    private function analyzeTranslations(array $translations): array
    {
        $toImport = [];
        $toUpdate = [];
        $unchanged = [];
        $maliciousRows = [];
        $errors = [];
        $candidates = [];

        foreach ($translations as $translation) {
            if (!isset($translation['translationKey']) || $translation['translationKey'] === '') {
                $errors[] = [
                    'rowNumber' => $translation['_rowNumber'] ?? null,
                    'translationKey' => '',
                    'translation' => $translation['translation'] ?? '',
                    'context' => $translation['context'] ?? 'site',
                    'error' => Craft::t('translation-manager', 'Missing Translation Key'),
                ];
                continue;
            }

            $targetLanguage = null;
            if (!empty($translation['language'])) {
                $targetLanguage = $translation['language'];
            } elseif (!empty($translation['siteId'])) {
                $siteId = (int)$translation['siteId'];
                $site = Craft::$app->getSites()->getSiteById($siteId);
                if ($site) {
                    $targetLanguage = $site->language;
                } else {
                    $errors[] = [
                        'rowNumber' => $translation['_rowNumber'] ?? null,
                        'translationKey' => $translation['translationKey'],
                        'translation' => $translation['translation'] ?? '',
                        'context' => $translation['context'] ?? 'site',
                        'siteId' => $siteId,
                        'language' => 'unknown',
                        'error' => Craft::t('translation-manager', 'Invalid site ID: {siteId} does not exist', ['siteId' => $siteId]),
                    ];
                    $this->logWarning("Invalid site ID for translation", [
                        'siteId' => $siteId,
                        'translationKey' => $translation['translationKey'],
                    ]);
                    continue;
                }
            }

            if (!$targetLanguage) {
                $targetLanguage = Craft::$app->getSites()->getPrimarySite()->language;
            }
            $targetLanguage = TranslationManager::getInstance()->getSettings()->mapLanguage($targetLanguage);
            if (!$this->isAllowedImportLanguage($targetLanguage)) {
                $errors[] = [
                    'rowNumber' => $translation['_rowNumber'] ?? null,
                    'translationKey' => $translation['translationKey'] ?? '',
                    'translation' => $translation['translation'] ?? '',
                    'context' => $translation['context'] ?? 'site',
                    'language' => $targetLanguage,
                    'error' => Craft::t('translation-manager', "Language '{language}' is not allowed for import.", ['language' => $targetLanguage]),
                ];
                continue;
            }

            $originalKey = $translation['translationKey'];
            $originalTranslation = $translation['translation'] ?? '';
            $originalContext = $translation['context'] ?? 'site';

            $isMalicious = false;
            $detectedThreats = [];
            $fieldsToCheck = [
                'Translation Key' => $originalKey,
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
                    'translationKey' => $originalKey,
                    'translation' => $originalTranslation,
                    'context' => $originalContext,
                    'threats' => $detectedThreats,
                ];
                $this->logWarning("Malicious content detected in translation", [
                    'translationKey' => $originalKey,
                    'threats' => array_keys($detectedThreats),
                ]);
                continue;
            }

            $keyText = $translation['translationKey'];
            $translationText = $translation['translation'] ?? '';
            $context = isset($translation['context']) ? StringHelper::stripHtml($translation['context']) : 'site';

            $keyText = CsvImportHelper::stripFormulaEscapePrefix($keyText);
            $translationText = CsvImportHelper::stripFormulaEscapePrefix($translationText);
            $context = CsvImportHelper::stripFormulaEscapePrefix($context);

            $keyText = $this->stripDangerousImportContent((string)$keyText);
            $translationText = $this->stripDangerousImportContent((string)$translationText);

            if (empty($context)) {
                $context = 'site';
            }

            $category = isset($translation['category']) ? StringHelper::stripHtml($translation['category']) : '';
            $category = CsvImportHelper::stripFormulaEscapePrefix($category);
            $type = isset($translation['type']) ? strtolower(trim($translation['type'])) : '';

            $category = $this->normalizeImportedCategory($category, $context, $type);

            $sourceHash = md5($keyText);
            $candidates[] = [
                'rowNumber' => $translation['_rowNumber'] ?? null,
                'translationKey' => $keyText,
                'translation' => $translationText,
                'context' => $context,
                'category' => $category,
                'language' => $targetLanguage,
                'type' => $type,
                'status' => $translation['status'] ?? '',
                'origin' => $translation['origin'] ?? '',
                'siteId' => $translation['siteId'] ?? '',
                'sourceHash' => $sourceHash,
            ];
        }

        $existingTranslations = $this->findExistingTranslationsForCandidates($candidates);

        foreach ($candidates as $candidate) {
            $existing = $existingTranslations[$this->translationLookupKey(
                (string)$candidate['sourceHash'],
                (string)$candidate['language'],
                (string)$candidate['category'],
            )] ?? null;

            if ($existing) {
                $dbValue = $existing->translation;
                $csvValue = (string)$candidate['translation'];

                $dbNormalized = $dbValue === null ? '' : $dbValue;

                if ($dbNormalized === $csvValue) {
                    $unchanged[] = [
                        'rowNumber' => $candidate['rowNumber'],
                        'translationKey' => $candidate['translationKey'],
                        'translation' => $candidate['translation'],
                        'context' => $candidate['context'],
                        'category' => $candidate['category'],
                        'currentTranslation' => $existing->translation,
                        'currentStatus' => $existing->status,
                        'existingContext' => $existing->context,
                        'language' => $candidate['language'],
                        'type' => $candidate['type'],
                        'status' => $candidate['status'],
                        'origin' => $candidate['origin'],
                        'siteId' => $candidate['siteId'],
                    ];
                } else {
                    $toUpdate[] = [
                        'rowNumber' => $candidate['rowNumber'],
                        'translationKey' => $candidate['translationKey'],
                        'translation' => $candidate['translation'],
                        'currentTranslation' => $existing->translation ?? '',
                        'context' => $candidate['context'],
                        'category' => $candidate['category'],
                        'currentStatus' => $existing->status,
                        'existingContext' => $existing->context,
                        'language' => $candidate['language'],
                        'type' => $candidate['type'],
                        'status' => $candidate['status'],
                        'origin' => $candidate['origin'],
                        'siteId' => $candidate['siteId'],
                    ];
                }
            } else {
                $toImport[] = [
                    'rowNumber' => $candidate['rowNumber'],
                    'translationKey' => $candidate['translationKey'],
                    'translation' => $candidate['translation'],
                    'context' => $candidate['context'],
                    'category' => $candidate['category'],
                    'language' => $candidate['language'],
                    'type' => $candidate['type'],
                    'status' => $candidate['status'],
                    'origin' => $candidate['origin'],
                    'siteId' => $candidate['siteId'],
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
     * Import normalized translation rows from the preview flow.
     *
     * @param array<int,array<string,mixed>> $translations
     */
    private function importTranslations(array $translations, bool $includeDetails = false): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $details = $includeDetails ? [
            'imported' => [],
            'updated' => [],
        ] : null;
        $translationService = TranslationManager::getInstance()->translations;
        $userId = Craft::$app->getUser()->getId();
        $candidates = [];

        foreach ($translations as $translation) {
            $rowNumber = (int)($translation['rowNumber'] ?? $translation['_rowNumber'] ?? 0);

            try {
                $keyText = (string)($translation['translationKey'] ?? '');
                $translationText = (string)($translation['translation'] ?? '');
                $context = (string)($translation['context'] ?? 'site');
                $category = (string)($translation['category'] ?? '');
                $type = strtolower(trim((string)($translation['type'] ?? '')));
                $language = (string)($translation['language'] ?? '');

                $keyText = CsvImportHelper::stripFormulaEscapePrefix($keyText);
                $translationText = CsvImportHelper::stripFormulaEscapePrefix($translationText);
                $context = CsvImportHelper::stripFormulaEscapePrefix($context);
                $category = CsvImportHelper::stripFormulaEscapePrefix($category);

                $threats = [];
                if ($this->containsMaliciousContent($keyText, $threats)
                    || $this->containsMaliciousContent($translationText, $threats)
                    || $this->containsMaliciousContent($context, $threats)) {
                    $errors[] = Craft::t('translation-manager', 'Row {row}: Malicious content blocked ({threats})', [
                        'row' => $rowNumber ?: '?',
                        'threats' => implode(', ', $threats),
                    ]);
                    $skipped++;
                    continue;
                }

                if ($keyText === '') {
                    $skipped++;
                    continue;
                }

                if ($language === '' && !empty($translation['siteId'])) {
                    $site = Craft::$app->getSites()->getSiteById((int)$translation['siteId']);
                    $language = $site ? $site->language : '';
                }
                if ($language === '') {
                    $language = Craft::$app->getSites()->getPrimarySite()->language;
                }
                $language = TranslationManager::getInstance()->getSettings()->mapLanguage($language);
                if (!$this->isAllowedImportLanguage($language)) {
                    $errors[] = "Row {$rowNumber}: Language '{$language}' is not allowed for import";
                    $skipped++;
                    continue;
                }

                $siteId = SiteLanguageHelper::getSiteIdForLanguage($language);

                $keyText = $this->stripDangerousImportContent($keyText);
                $translationText = $this->stripDangerousImportContent($translationText);
                $context = $this->stripDangerousImportContent($context);

                $context = StringHelper::stripHtml($context);
                $category = StringHelper::stripHtml($category);

                if ($context === '') {
                    $context = 'site';
                }
                $category = $this->normalizeImportedCategory($category, $context, $type);

                if (strlen($keyText) > 5000 || strlen($translationText) > 5000) {
                    $errors[] = "Row {$rowNumber}: Text too long (max 5000 characters)";
                    $this->logWarning('Import validation failed: Text too long', [
                        'rowNumber' => $rowNumber,
                        'keyLength' => strlen($keyText),
                        'translationLength' => strlen($translationText),
                    ]);
                    $skipped++;
                    continue;
                }

                $importedStatus = $this->normalizeImportedStatus(isset($translation['status']) ? (string)$translation['status'] : null);
                $importedOrigin = $this->normalizeImportedOrigin(isset($translation['origin']) ? (string)$translation['origin'] : null);

                $candidates[] = [
                    'rowNumber' => $rowNumber,
                    'translationKey' => $keyText,
                    'translation' => $translationText,
                    'context' => $context,
                    'category' => $category,
                    'language' => $language,
                    'siteId' => $siteId,
                    'sourceHash' => md5($keyText),
                    'importedStatus' => $importedStatus,
                    'importedOrigin' => $importedOrigin,
                ];
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        $existingTranslations = $this->findExistingTranslationsForCandidates($candidates);

        foreach ($candidates as $candidate) {
            $rowNumber = (int)$candidate['rowNumber'];
            $keyText = (string)$candidate['translationKey'];
            $translationText = (string)$candidate['translation'];
            $context = (string)$candidate['context'];
            $category = (string)$candidate['category'];
            $language = (string)$candidate['language'];
            $siteId = (int)$candidate['siteId'];
            $sourceHash = (string)$candidate['sourceHash'];
            $importedStatus = $candidate['importedStatus'];
            $importedOrigin = $candidate['importedOrigin'];
            $lookupKey = $this->translationLookupKey($sourceHash, $language, $category);
            $translationRecord = null;

            try {
                $translationRecord = $existingTranslations[$lookupKey] ?? null;

                $isNew = false;
                $previousTranslation = '';
                if ($translationRecord) {
                    $previousTranslation = $translationRecord->translation ?? '';
                    if ($previousTranslation === $translationText) {
                        $skipped++;
                        continue;
                    }
                } else {
                    $translationRecord = new TranslationRecord();
                    $translationRecord->source = $keyText;
                    $translationRecord->sourceHash = $sourceHash;
                    $translationRecord->siteId = $siteId;
                    $translationRecord->language = $language;
                    $translationRecord->context = $context;
                    $translationRecord->category = $category;
                    $translationRecord->translationKey = $keyText;
                    $translationRecord->usageCount = 1;
                    $translationRecord->lastUsed = Db::prepareDateForDb(new \DateTime());
                    $translationRecord->dateCreated = Db::prepareDateForDb(new \DateTime());
                    $isNew = true;
                }

                if (!$translationRecord->source) {
                    $translationRecord->source = $keyText;
                }
                $translationRecord->translation = $translationText;
                if ($importedStatus !== null) {
                    $translationRecord->status = $importedStatus;
                } elseif ($isNew) {
                    $translationRecord->status = $translationText ? 'translated' : 'pending';
                } elseif (!in_array($translationRecord->status, ['unused', 'draft'], true)) {
                    $translationRecord->status = $translationText ? 'translated' : 'pending';
                }
                $translationRecord->translationOrigin = $importedOrigin ?? 'import';
                $translationRecord->createdByUserId = $userId;

                if ($translationRecord->status === 'translated') {
                    $translationRecord->reviewedByUserId = $userId;
                    $translationRecord->reviewedAt = Db::prepareDateForDb(new \DateTime());
                } else {
                    $translationRecord->reviewedByUserId = null;
                    $translationRecord->reviewedAt = null;
                }

                if ($this->getIntegrationService()->getIntegrationForContext($context) !== null && $translationRecord->context !== $context) {
                    if (substr_count($context, '.') > substr_count($translationRecord->context, '.')) {
                        $translationRecord->context = $context;
                    }
                }

                $translationRecord->dateUpdated = Db::prepareDateForDb(new \DateTime());

                $saved = $isNew ? $translationRecord->save() : $translationService->saveTranslation($translationRecord);
                if ($saved) {
                    if ($isNew) {
                        $imported++;
                        $this->logInfo('Import: Created new translation', [
                            'key' => $keyText,
                            'siteId' => $siteId,
                        ]);
                    } else {
                        $updated++;
                        $this->logInfo('Import: Updated translation', [
                            'key' => $keyText,
                            'siteId' => $siteId,
                        ]);
                    }

                    if ($includeDetails && $details !== null && count($details[$isNew ? 'imported' : 'updated']) < 50) {
                        $details[$isNew ? 'imported' : 'updated'][] = [
                            'key' => $keyText,
                            'translation' => $translationText,
                            'context' => $context,
                            'siteId' => $siteId,
                            'previousTranslation' => $previousTranslation,
                        ];
                    }
                } else {
                    $validationErrors = $translationRecord->getFirstErrors();
                    $errorMsg = !empty($validationErrors) ? implode(', ', $validationErrors) : 'Failed to save translation';
                    $errors[] = "Row {$rowNumber}: {$errorMsg} (Key: " . substr($keyText, 0, 50) . '...)';
                    $this->logWarning('Import: Failed to save translation', [
                        'key' => $keyText,
                        'siteId' => $siteId,
                        'error' => $errorMsg,
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }

            if (!$translationRecord->getIsNewRecord()) {
                $existingTranslations[$lookupKey] = $translationRecord;
            }
        }

        $result = [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
        ];

        if ($includeDetails) {
            $result['details'] = $details;
        }

        return $result;
    }

    private function stripDangerousImportContent(string $value): string
    {
        foreach (self::DANGEROUS_IMPORT_PATTERNS as $pattern) {
            $value = preg_replace($pattern, '', $value) ?? '';
        }

        return $value;
    }

    private function getIntegrationService(): IntegrationService
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');

        return $integrationService;
    }

    private function normalizeImportedCategory(string $category, string $context, string $type): string
    {
        $integrationService = $this->getIntegrationService();
        $contextCategory = $integrationService->getCategoryForContext($context);
        if ($contextCategory !== null) {
            return $contextCategory;
        }

        if ($category !== '') {
            return $category;
        }

        if ($type === 'forms') {
            $formIntegrations = $integrationService->getIntegrationsBySourceType('forms');
            if (count($formIntegrations) === 1) {
                $integration = reset($formIntegrations);
                if ($integration !== false) {
                    return $integration->getCategory();
                }
            }
        }

        return 'messages';
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     * @return array<string,TranslationRecord>
     */
    private function findExistingTranslationsForCandidates(array $candidates): array
    {
        $sourceHashes = [];
        $languages = [];
        $categories = [];

        foreach ($candidates as $candidate) {
            $sourceHash = (string)($candidate['sourceHash'] ?? '');
            $language = (string)($candidate['language'] ?? '');
            $category = (string)($candidate['category'] ?? '');

            if ($sourceHash === '' || $language === '' || $category === '') {
                continue;
            }

            $sourceHashes[] = $sourceHash;
            $languages[] = $language;
            $categories[] = $category;
        }

        $sourceHashes = array_values(array_unique($sourceHashes));
        $languages = array_values(array_unique($languages));
        $categories = array_values(array_unique($categories));

        if ($sourceHashes === [] || $languages === [] || $categories === []) {
            return [];
        }

        /** @var TranslationRecord[] $records */
        $records = TranslationRecord::find()
            ->where(['sourceHash' => $sourceHashes])
            ->andWhere(['language' => $languages])
            ->andWhere(['category' => $categories])
            ->all();

        $indexed = [];
        foreach ($records as $record) {
            $indexed[$this->translationLookupKey($record->sourceHash, (string)$record->language, $record->category)] = $record;
        }

        return $indexed;
    }

    private function translationLookupKey(string $sourceHash, string $language, string $category): string
    {
        return $sourceHash . "\n" . $language . "\n" . $category;
    }

    /**
     * Normalize and validate imported status from CSV.
     */
    private function normalizeImportedStatus(?string $rawStatus): ?string
    {
        if ($rawStatus === null) {
            return null;
        }

        $status = strtolower(trim($rawStatus));
        if ($status === '') {
            return null;
        }

        // Accept label-style values from CSV exports too.
        if ($status === 'ai draft' || $status === 'ai_draft') {
            $status = 'draft';
        }
        if ($status === 'approved') {
            $status = 'translated';
        }

        $allowed = ['pending', 'draft', 'translated', 'unused'];
        return in_array($status, $allowed, true) ? $status : null;
    }

    /**
     * Normalize and validate imported origin from CSV.
     */
    private function normalizeImportedOrigin(?string $rawOrigin): ?string
    {
        if ($rawOrigin === null) {
            return null;
        }

        $origin = strtolower(trim($rawOrigin));
        if ($origin === '') {
            return null;
        }

        $allowed = ['ai', 'manual', 'import', 'system'];
        return in_array($origin, $allowed, true) ? $origin : null;
    }
    
    /**
     * Import history page
     *
     * @return Response
     */
    public function actionHistory(): Response
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:manageImportExport'))) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to view import history.'));
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
     */
    public function actionLogMalicious(): Response
    {
        $this->requirePostRequest();

        // Require permission to import translations
        if (!Craft::$app->getUser()->checkPermission('translationManager:importTranslations')) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to import translations.'));
        }

        $maliciousRows = Craft::$app->getRequest()->getBodyParam('maliciousRows', []);
        
        foreach ($maliciousRows as $row) {
            $translationKey = $row['translationKey'] ?? '';
            $threats = $row['threats'] ?? [];
            $threatList = [];
            
            foreach ($threats as $field => $fieldThreats) {
                $threatList[] = $field . ': ' . implode(', ', $fieldThreats);
            }
            
            $this->logWarning("Malicious content blocked in import", [
                'translationKey' => $translationKey,
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
     */
    public function actionClearLogs(): Response
    {
        $this->requirePostRequest();
        
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser || (!Craft::$app->getUser()->checkPermission('translationManager:clearImportHistory'))) {
            if ($this->request->getAcceptsJson()) {
                return $this->asJson(['success' => false, 'error' => Craft::t('translation-manager', 'User does not have permission to clear import logs.')]);
            }
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to clear import logs.'));
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
                return $this->asJson(['success' => false, 'error' => Craft::t('translation-manager', 'Failed to clear import logs.')]);
            }
            
            Craft::$app->getSession()->setError(Craft::t('translation-manager', 'Failed to clear import logs.'));
            return $this->redirect('translation-manager/settings/import-export#history');
        }
    }
    
    /**
     * Check whether a language is allowed for import.
     */
    private function isAllowedImportLanguage(string $language): bool
    {
        $normalized = strtolower(trim($language));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, $this->getAllowedImportLanguages(), true);
    }

    /**
     * Get canonical language codes allowed for import.
     *
     * Includes mapped target locales and canonical site locales.
     *
     * @return array<int,string>
     */
    private function getAllowedImportLanguages(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $allowed = [];

        foreach (TranslationManager::getInstance()->getAllowedSites() as $site) {
            $allowed[] = strtolower($settings->mapLanguage($site->language));
        }

        foreach ($settings->getActiveLocaleMapping() as $source => $target) {
            $allowed[] = strtolower($target);
        }

        return array_values(array_unique(array_filter($allowed)));
    }
}
