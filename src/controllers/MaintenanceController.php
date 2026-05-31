<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for maintenance tasks like cleanup and optimization
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\web\Controller;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\helpers\SiteLanguageHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\Response;

/**
 * Maintenance Controller
 *
 * @since 1.0.0
 */
class MaintenanceController extends Controller
{
    use LoggingTrait;

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Check granular permissions based on action
        $user = Craft::$app->getUser();

        switch ($action->id) {
            case 'clean-unused':
            case 'clean-unused-type':
            case 'clean-languages':
            case 'clean-categories':
                if (!$user->checkPermission('translationManager:cleanUnused')) {
                    throw new \yii\web\ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to clean unused translations.'));
                }
                break;
            case 'scan-templates-action':
                if (!$user->checkPermission('translationManager:scanTemplates')) {
                    throw new \yii\web\ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to scan templates.'));
                }
                break;
            case 'recapture-formie':
                if (!$user->checkPermission('translationManager:recaptureFormie')) {
                    throw new \yii\web\ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to recapture Formie translations.'));
                }
                break;
            default:
                // Index page - allow if user has ANY maintenance-related permission
                $hasAccess =
                    $user->checkPermission('translationManager:maintenance') ||
                    $user->checkPermission('translationManager:cleanUnused') ||
                    $user->checkPermission('translationManager:scanTemplates') ||
                    $user->checkPermission('translationManager:recaptureFormie') ||
                    $user->checkPermission('translationManager:clearTranslations') ||
                    $user->checkPermission('translationManager:clearFormie') ||
                    $user->checkPermission('translationManager:clearSite') ||
                    $user->checkPermission('translationManager:clearAll');

                if (!$hasAccess) {
                    throw new \yii\web\ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to access maintenance.'));
                }
        }

        return parent::beforeAction($action);
    }

    /**
     * Maintenance index page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $settings = TranslationManager::getInstance()->getSettings();

        return $this->renderTemplate('translation-manager/maintenance/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Clean up unused translations
     *
     * @return Response
     */
    public function actionCleanUnused(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('translationManager:cleanUnused');
        
        $translationsService = TranslationManager::getInstance()->translations;
        
        try {
            // Create backup if enabled
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled) {
                try {
                    $backupService = TranslationManager::getInstance()->backup;
                    $backupPath = $backupService->createBackup('before_cleanup');
                    if ($backupPath) {
                        $this->logInfo("Created backup before cleaning unused translations", ['backupPath' => $backupPath]);
                    }
                } catch (\Exception $e) {
                    $this->logError("Failed to create backup before cleaning unused translations", ['error' => $e->getMessage()]);
                    // Continue with the operation even if backup fails
                }
            }
            
            $deleted = $translationsService->cleanUnusedTranslations();
            
            // Check if this is an AJAX request
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'deleted' => $deleted,
                    'message' => $deleted > 0
                        ? Craft::t('translation-manager', 'Cleaned up {count} unused translation(s) and regenerated translation files.', ['count' => $deleted])
                        : Craft::t('translation-manager', 'No unused translations found.'),
                ]);
            }
            
            // Regular form submission
            if ($deleted > 0) {
                Craft::$app->getSession()->setNotice(
                    Craft::t('translation-manager', 'Cleaned up {count} unused translation(s) and regenerated translation files.', [
                        'count' => $deleted,
                    ])
                );
            } else {
                Craft::$app->getSession()->setNotice(
                    Craft::t('translation-manager', 'No unused translations found.')
                );
            }
        } catch (\Exception $e) {
            // Check if this is an AJAX request
            if (Craft::$app->request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('translation-manager', 'Failed to clean up translations: {error}', [
                        'error' => $e->getMessage(),
                    ]),
                ]);
            }
            
            // Regular form submission
            Craft::$app->getSession()->setError(
                Craft::t('translation-manager', 'Failed to clean up translations: {error}', [
                    'error' => $e->getMessage(),
                ])
            );
        }
        
        return $this->redirectToPostedUrl();
    }

    /**
     * Force recapture all Formie translations
     *
     * @return Response
     */
    public function actionRecaptureFormie(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:recaptureFormie');

        try {
            $count = 0;
            $pluginName = TranslationManager::getFormiePluginName();

            if (PluginHelper::isPluginEnabled('formie')) {
                /** @var IntegrationService $integrationService */
                $integrationService = TranslationManager::getInstance()->get('integrations');
                $formieIntegration = $integrationService->get('formie');
                if ($formieIntegration === null) {
                    return $this->asJson([
                        'success' => false,
                        'error' => Craft::t('translation-manager', 'Formie integration is not available.'),
                    ]);
                }

                $forms = \verbb\formie\Formie::getInstance()->getForms()->getAllForms();

                foreach ($forms as $form) {
                    $formieIntegration->captureTranslations($form);
                    $count++;
                }

                $formieIntegration->checkUsage();
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('translation-manager', 'Recaptured {name} translations from {count} form(s)', ['name' => $pluginName, 'count' => $count]),
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Failed to recapture translations: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }
    
    /**
     * Clean unused translations by type
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionCleanUnusedType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:cleanUnused');

        $request = Craft::$app->getRequest();
        $type = $request->getBodyParam('type');

        // Handle per-category types (category:xxx)
        $category = null;
        if (str_starts_with($type, 'category:')) {
            $category = substr($type, 9);
            $type = 'category';
        }

        if (!in_array($type, ['all', 'category', 'formie'])) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid type specified'),
            ]);
        }

        try {
            $query = new \craft\db\Query();
            $query->from('{{%translationmanager_translations}}')
                  ->where(['status' => 'unused']);

            $displayType = $type;
            switch ($type) {
                case 'category':
                    // Filter by specific category
                    $query->andWhere(['category' => $category]);
                    $displayType = $category;
                    break;
                case 'formie':
                    $query->andWhere(['or',
                        ['like', 'context', 'formie.%', false],
                        ['=', 'context', 'formie'],
                    ]);
                    break;
                case 'all':
                    // No additional filter needed
                    break;
            }

            /** @var \lindemannrock\translationmanager\records\TranslationRecord[] $unusedTranslations */
            $unusedTranslations = $query->all();
            $count = count($unusedTranslations);
            
            if ($count === 0) {
                return $this->asJson([
                    'success' => true,
                    'message' => Craft::t('translation-manager', 'No unused {type} translations found.', ['type' => $displayType]),
                ]);
            }

            // Create backup if enabled
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled) {
                try {
                    $backupService = TranslationManager::getInstance()->backup;
                    $backupPath = $backupService->createBackup("before_cleanup_{$displayType}");
                    $this->logInfo("Created backup before cleaning unused translations", [
                        'type' => $displayType,
                        'backupPath' => $backupPath,
                    ]);
                } catch (\Exception $e) {
                    $this->logError("Failed to create backup", ['error' => $e->getMessage()]);
                }
            }

            $deleted = TranslationManager::getInstance()->translations->deleteTranslations(
                array_column($unusedTranslations, 'id')
            );

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('translation-manager', 'Deleted {count} unused {type} translations.', ['count' => $deleted, 'type' => $displayType]),
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Failed to clean unused translations: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }

    /**
     * Remove translations for selected language codes (mapped-source and ghost locales).
     *
     * @return Response
     * @since 5.22.0
     */
    public function actionCleanLanguages(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:cleanUnused');

        $request = Craft::$app->getRequest();
        $languages = $request->getBodyParam('languages', []);
        if (!is_array($languages)) {
            $languages = [];
        }

        $languages = array_values(array_unique(array_filter(array_map(static fn($value) => trim((string)$value), $languages))));
        $mode = (string)$request->getBodyParam('mode', 'delete');
        if (!in_array($mode, ['delete', 'migrate'], true)) {
            $mode = 'delete';
        }

        if (empty($languages)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'No languages selected.'),
            ]);
        }

        $candidates = $this->getLanguageCleanupCandidates();
        $allowed = [];
        $mappedTargets = [];
        foreach ($candidates['mappedSource'] as $row) {
            $language = (string)($row['language'] ?? '');
            $allowed[] = $language;
            $mappedTargets[$language] = (string)($row['mappedTo'] ?? '');
        }
        foreach ($candidates['ghost'] as $row) {
            $allowed[] = (string)($row['language'] ?? '');
        }
        $allowed = array_values(array_unique(array_filter($allowed)));

        $invalid = array_values(array_diff($languages, $allowed));
        if (!empty($invalid)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid language selection: {languages}', ['languages' => implode(', ', $invalid)]),
            ]);
        }

        if ($mode === 'migrate') {
            $unmapped = array_values(array_filter($languages, static fn(string $language): bool => !isset($mappedTargets[$language]) || $mappedTargets[$language] === ''));
            if (!empty($unmapped)) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('translation-manager', 'Migration is only available for mapped-source languages: {languages}', ['languages' => implode(', ', $unmapped)]),
                ]);
            }
        }

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled) {
                try {
                    $backupService = TranslationManager::getInstance()->backup;
                    $backupService->createBackup('before_cleanup_languages');
                } catch (\Exception $e) {
                    $this->logError("Failed to create backup before language cleanup", ['error' => $e->getMessage()]);
                }
            }

            $rows = (new \craft\db\Query())
                ->select([
                    'id',
                    'source',
                    'sourceHash',
                    'context',
                    'category',
                    'siteId',
                    'language',
                    'translationKey',
                    'translation',
                    'status',
                    'translationOrigin',
                    'createdByUserId',
                    'reviewedByUserId',
                    'reviewedAt',
                    'usageCount',
                    'lastUsed',
                    'dateCreated',
                    'dateUpdated',
                ])
                ->from('{{%translationmanager_translations}}')
                ->where(['language' => $languages])
                ->all();

            if (empty($rows)) {
                return $this->asJson([
                    'success' => true,
                    'deleted' => 0,
                    'message' => Craft::t('translation-manager', 'No matching translations found for selected languages.'),
                ]);
            }

            $migrated = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0];
            if ($mode === 'migrate') {
                $migrated = $this->migrateMappedSourceLanguageRows($rows, $mappedTargets);
            }

            $ids = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $rows)));
            $deleted = TranslationManager::getInstance()->translations->deleteTranslations($ids);

            $counts = [];
            foreach ($rows as $row) {
                $lang = (string)$row['language'];
                $counts[$lang] = ($counts[$lang] ?? 0) + 1;
            }
            ksort($counts);

            return $this->asJson([
                'success' => true,
                'deleted' => $deleted,
                'deletedByLanguage' => $counts,
                'migrated' => $migrated,
                'message' => $mode === 'migrate'
                    ? Craft::t('translation-manager', 'Migrated {created} new and {updated} existing translation(s), skipped {skipped}, found {conflicts} conflict(s), then deleted {deleted} mapped-source row(s).', [
                        'created' => $migrated['created'],
                        'updated' => $migrated['updated'],
                        'skipped' => $migrated['skipped'],
                        'conflicts' => $migrated['conflicts'],
                        'deleted' => $deleted,
                    ])
                    : Craft::t('translation-manager', 'Deleted {count} translations across {langCount} language(s).', ['count' => $deleted, 'langCount' => count($counts)]),
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Failed to clean languages: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }

    /**
     * Remove translations for selected removed/disabled categories.
     *
     * @return Response
     * @since 5.22.0
     */
    public function actionCleanCategories(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:cleanUnused');

        $request = Craft::$app->getRequest();
        $categories = $request->getBodyParam('categories', []);
        if (!is_array($categories)) {
            $categories = [];
        }

        $categories = array_values(array_unique(array_filter(array_map(static fn($value) => trim((string)$value), $categories))));
        if (empty($categories)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'No categories selected.'),
            ]);
        }

        $candidateRows = $this->getCategoryCleanupCandidates()['removed'];
        $allowed = array_values(array_unique(array_filter(array_map(static fn(array $row) => (string)($row['category'] ?? ''), $candidateRows))));

        $invalid = array_values(array_diff($categories, $allowed));
        if (!empty($invalid)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid category selection: {categories}', ['categories' => implode(', ', $invalid)]),
            ]);
        }

        try {
            $settings = TranslationManager::getInstance()->getSettings();
            if ($settings->backupEnabled) {
                try {
                    $backupService = TranslationManager::getInstance()->backup;
                    $backupService->createBackup('before_cleanup_categories');
                } catch (\Exception $e) {
                    $this->logError("Failed to create backup before category cleanup", ['error' => $e->getMessage()]);
                }
            }

            $rows = (new \craft\db\Query())
                ->select(['id', 'category'])
                ->from('{{%translationmanager_translations}}')
                ->where(['category' => $categories])
                ->all();

            if (empty($rows)) {
                return $this->asJson([
                    'success' => true,
                    'deleted' => 0,
                    'message' => Craft::t('translation-manager', 'No matching translations found for selected categories.'),
                ]);
            }

            $ids = array_values(array_unique(array_map(static fn($row) => (int)$row['id'], $rows)));
            $deleted = TranslationManager::getInstance()->translations->deleteTranslations($ids);

            $counts = [];
            foreach ($rows as $row) {
                $category = (string)$row['category'];
                $counts[$category] = ($counts[$category] ?? 0) + 1;
            }
            ksort($counts);

            return $this->asJson([
                'success' => true,
                'deleted' => $deleted,
                'deletedByCategory' => $counts,
                'message' => Craft::t('translation-manager', 'Deleted {count} translations across {catCount} category(s).', ['count' => $deleted, 'catCount' => count($counts)]),
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Failed to clean categories: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }
    
    /**
     * Scan templates action
     *
     * @return Response
     * @since 5.0.0
     */
    public function actionScanTemplatesAction(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('translationManager:scanTemplates');
        
        try {
            $results = TranslationManager::getInstance()->translations->scanTemplatesForUnused();
            
            $message = Craft::t('translation-manager', 'Template scan complete: {files} files scanned, {unused} marked as unused', [
                'files' => $results['scanned_files'],
                'unused' => $results['marked_unused'],
            ]);
            if (isset($results['created']) && $results['created'] > 0) {
                $message .= ', ' . Craft::t('translation-manager', '{count} created', ['count' => $results['created']]);
            }
            if ($results['reactivated'] > 0) {
                $message .= ', ' . Craft::t('translation-manager', '{count} reactivated', ['count' => $results['reactivated']]);
            }
            
            return $this->asJson([
                'success' => true,
                'message' => $message,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Failed to scan templates: {error}', ['error' => $e->getMessage()]),
            ]);
        }
    }

    /**
     * Get cleanup candidates for language-level cleanup.
     *
     * @return array{mappedSource: array<int, array<string,mixed>>, ghost: array<int, array<string,mixed>>, totalCandidates:int, totalRows:int}
     */
    private function getLanguageCleanupCandidates(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();

        $languageCounts = (new \craft\db\Query())
            ->select(['language', 'COUNT(*) as count'])
            ->from('{{%translationmanager_translations}}')
            ->groupBy(['language'])
            ->all();

        $activeMapping = $settings->getActiveLocaleMapping();
        $mappedSources = array_keys($activeMapping);

        $canonicalLocales = [];
        foreach (TranslationManager::getInstance()->getAllowedSites() as $site) {
            $canonicalLocales[] = $settings->mapLanguage($site->language);
        }
        foreach ($activeMapping as $target) {
            $canonicalLocales[] = $target;
        }
        $canonicalLocales = array_values(array_unique(array_filter($canonicalLocales)));

        $canonicalLookup = array_fill_keys(array_map('strtolower', $canonicalLocales), true);
        $mappedSourceLookup = array_fill_keys(array_map('strtolower', $mappedSources), true);

        $result = [
            'mappedSource' => [],
            'ghost' => [],
            'totalCandidates' => 0,
            'totalRows' => 0,
        ];

        foreach ($languageCounts as $row) {
            $language = (string)($row['language'] ?? '');
            $count = (int)($row['count'] ?? 0);
            if ($language === '' || $count <= 0) {
                continue;
            }

            $normalized = strtolower($language);
            $entry = [
                'language' => $language,
                'count' => $count,
            ];

            if (isset($mappedSourceLookup[$normalized])) {
                $entry['mappedTo'] = $activeMapping[$language] ?? $settings->mapLanguage($language);
                $result['mappedSource'][] = $entry;
                $result['totalCandidates']++;
                $result['totalRows'] += $count;
                continue;
            }

            if (!isset($canonicalLookup[$normalized])) {
                $result['ghost'][] = $entry;
                $result['totalCandidates']++;
                $result['totalRows'] += $count;
            }
        }

        return $result;
    }

    /**
     * Copy mapped-source language rows into their canonical target before deletion.
     *
     * Existing canonical translation text is never overwritten. If the target row
     * exists but its translation is empty, useful source text is copied over.
     *
     * @param array<int, array<string,mixed>> $sourceRows
     * @param array<string,string> $mappedTargets
     * @return array{created:int, updated:int, skipped:int, conflicts:int}
     */
    private function migrateMappedSourceLanguageRows(array $sourceRows, array $mappedTargets): array
    {
        $sourceRows = array_values(array_filter($sourceRows, static fn(array $row): bool => isset($mappedTargets[(string)($row['language'] ?? '')])));
        if (empty($sourceRows)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0];
        }

        $sourceHashes = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string)($row['sourceHash'] ?? ''), $sourceRows))));
        $targetLanguages = array_values(array_unique(array_filter(array_map(static fn(array $row): string => $mappedTargets[(string)$row['language']] ?? '', $sourceRows))));

        /** @var array<string, TranslationRecord> $existingTargets */
        $existingTargets = TranslationRecord::find()
            ->where(['sourceHash' => $sourceHashes])
            ->andWhere(['language' => $targetLanguages])
            ->indexBy(static fn(TranslationRecord $record): string => self::languageMigrationKey($record->sourceHash, (string)$record->language, $record->category))
            ->all();

        $now = Db::prepareDateForDb(new \DateTime());
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0];

        foreach ($sourceRows as $sourceRow) {
            $targetLanguage = $mappedTargets[(string)$sourceRow['language']] ?? '';
            if ($targetLanguage === '') {
                $counts['skipped']++;
                continue;
            }

            $key = self::languageMigrationKey((string)$sourceRow['sourceHash'], $targetLanguage, (string)$sourceRow['category']);
            $sourceTranslation = (string)($sourceRow['translation'] ?? '');
            $target = $existingTargets[$key] ?? null;

            if (!$target) {
                $target = new TranslationRecord();
                $target->source = (string)$sourceRow['source'];
                $target->sourceHash = (string)$sourceRow['sourceHash'];
                $target->context = (string)$sourceRow['context'];
                $target->category = (string)$sourceRow['category'];
                $target->translationKey = (string)$sourceRow['translationKey'];
                $target->translation = $sourceRow['translation'] !== null ? (string)$sourceRow['translation'] : null;
                $target->siteId = SiteLanguageHelper::getSiteIdForLanguage($targetLanguage);
                $target->language = $targetLanguage;
                $target->status = (string)$sourceRow['status'];
                $target->translationOrigin = (string)$sourceRow['translationOrigin'];
                $target->createdByUserId = $sourceRow['createdByUserId'] !== null ? (int)$sourceRow['createdByUserId'] : null;
                $target->reviewedByUserId = $sourceRow['reviewedByUserId'] !== null ? (int)$sourceRow['reviewedByUserId'] : null;
                $target->reviewedAt = $sourceRow['reviewedAt'] ?? null;
                $target->usageCount = (int)$sourceRow['usageCount'];
                $target->lastUsed = $sourceRow['lastUsed'] ?? null;
                $target->dateCreated = $sourceRow['dateCreated'] ?? $now;
                $target->dateUpdated = $now;
                $target->uid = StringHelper::UUID();
                if ($target->save()) {
                    $existingTargets[$key] = $target;
                    $counts['created']++;
                } else {
                    $counts['skipped']++;
                    $this->logError('Failed to migrate mapped-source translation row', [
                        'language' => $sourceRow['language'] ?? null,
                        'targetLanguage' => $targetLanguage,
                        'sourceHash' => $sourceRow['sourceHash'] ?? null,
                        'category' => $sourceRow['category'] ?? null,
                        'errors' => $target->getErrors(),
                    ]);
                }
                continue;
            }

            $targetTranslation = (string)($target->translation ?? '');
            if ($targetTranslation === '' && $sourceTranslation !== '') {
                $target->translation = $sourceTranslation;
                $target->status = (string)$sourceRow['status'];
                $target->translationOrigin = (string)$sourceRow['translationOrigin'];
                $target->createdByUserId = $sourceRow['createdByUserId'] !== null ? (int)$sourceRow['createdByUserId'] : null;
                $target->reviewedByUserId = $sourceRow['reviewedByUserId'] !== null ? (int)$sourceRow['reviewedByUserId'] : null;
                $target->reviewedAt = $sourceRow['reviewedAt'] ?? null;
                $target->dateUpdated = $now;
                if ($target->save()) {
                    $counts['updated']++;
                } else {
                    $counts['skipped']++;
                    $this->logError('Failed to update canonical translation during mapped-source migration', [
                        'language' => $sourceRow['language'] ?? null,
                        'targetLanguage' => $targetLanguage,
                        'sourceHash' => $sourceRow['sourceHash'] ?? null,
                        'category' => $sourceRow['category'] ?? null,
                        'errors' => $target->getErrors(),
                    ]);
                }
                continue;
            }

            if ($targetTranslation !== '' && $sourceTranslation !== '' && $targetTranslation !== $sourceTranslation) {
                $counts['conflicts']++;
                continue;
            }

            $counts['skipped']++;
        }

        return $counts;
    }

    private static function languageMigrationKey(string $sourceHash, string $language, string $category): string
    {
        return $sourceHash . "\n" . $language . "\n" . $category;
    }

    /**
     * Get cleanup candidates for removed/disabled categories.
     *
     * @return array{removed: array<int, array<string,mixed>>, totalCandidates:int, totalRows:int}
     */
    private function getCategoryCleanupCandidates(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();

        $categoryCounts = (new \craft\db\Query())
            ->select(['category', 'COUNT(*) as count'])
            ->from('{{%translationmanager_translations}}')
            ->groupBy(['category'])
            ->all();

        $enabledCategories = $settings->getEnabledCategories();
        if ($settings->enableFormieIntegration && !in_array('formie', $enabledCategories, true)) {
            $enabledCategories[] = 'formie';
        }
        $enabledLookup = array_fill_keys(array_map('strtolower', array_filter($enabledCategories)), true);

        $result = [
            'removed' => [],
            'totalCandidates' => 0,
            'totalRows' => 0,
        ];

        foreach ($categoryCounts as $row) {
            $category = (string)($row['category'] ?? '');
            $count = (int)($row['count'] ?? 0);
            if ($category === '' || $count <= 0) {
                continue;
            }

            $normalized = strtolower($category);
            if (isset($enabledLookup[$normalized])) {
                continue;
            }

            $result['removed'][] = [
                'category' => $category,
                'count' => $count,
            ];
            $result['totalCandidates']++;
            $result['totalRows'] += $count;
        }

        usort($result['removed'], static fn(array $a, array $b): int => strcmp($a['category'], $b['category']));

        return $result;
    }
}
