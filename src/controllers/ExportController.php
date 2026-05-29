<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for exporting translations as CSV/XLSX/JSON downloads
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\elements\User;
use craft\web\Controller;
use lindemannrock\base\helpers\ExportHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Export Controller
 *
 * @since 1.0.0
 */
class ExportController extends Controller
{
    use LoggingTrait;
    /**
     * @inheritdoc
     */
    protected array|int|bool $allowAnonymous = false;
    
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        if (!$user->checkPermission('translationManager:exportTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to export translations');
        }

        return parent::beforeAction($action);
    }

    /**
     * Export translations as CSV download
     * Respects filters when called from translations page
     * Exports all when called from settings page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        try {
            $request = Craft::$app->getRequest();
            $translationsService = TranslationManager::getInstance()->translations;
            $settings = TranslationManager::$plugin->getSettings();

            // Check if we have filters from the translations page
            $criteria = [];

            // Language filter
            $languageParam = $request->getParam('language') ?: $request->getBodyParam('language');
            $exportAll = empty($languageParam);

            if (!empty($languageParam)) {
                $languageParam = $settings->mapLanguage($languageParam);
                $criteria['language'] = $languageParam;
            } else {
                // Export all languages
                $criteria['allSites'] = true;
            }
            
            $typeParam = $request->getParam('type') ?: $request->getBodyParam('type');
            if ($typeParam && $typeParam !== 'all') {
                $criteria['type'] = $typeParam;
            }
            $statusParam = $request->getParam('status') ?: $request->getBodyParam('status');
            if ($statusParam && $statusParam !== 'all') {
                $criteria['status'] = $statusParam;
            }
            $originParam = $request->getParam('origin') ?: $request->getBodyParam('origin');
            if ($originParam && $originParam !== 'all') {
                $criteria['origin'] = $originParam;
            }
            $searchParam = $request->getParam('search') ?: $request->getBodyParam('search');
            if ($searchParam) {
                $criteria['search'] = $searchParam;
            }

            // Category filter
            $categoryParam = $request->getParam('category') ?: $request->getBodyParam('category');
            if ($categoryParam && $categoryParam !== 'all') {
                if ($categoryParam === 'formie') {
                    $criteria['type'] = 'forms';
                } else {
                    $criteria['type'] = 'site';
                    $criteria['category'] = $categoryParam;
                }
            }

            // Get translations with filters
            $translations = $translationsService->getTranslations($criteria);
            $allowedExportLanguages = $this->getAllowedExportLanguages();
            
            // If no translations found, still export empty CSV with headers
            $headers = [
                'Translation Key',
                'Translation',
                'Category',
                'Type',
                'Context',
                'Status',
                'Origin',
                'Language',
                'Created By',
                'Reviewed By',
                'Reviewed At',
                'Updated At',
            ];

            $rows = [];
            $userIds = [];
            foreach ($translations as $translation) {
                if (!empty($translation['createdByUserId'])) {
                    $userIds[] = (int) $translation['createdByUserId'];
                }
                if (!empty($translation['reviewedByUserId'])) {
                    $userIds[] = (int) $translation['reviewedByUserId'];
                }
            }

            $userEmailMap = $this->getUserEmailMap($userIds);

            foreach ($translations as $translation) {
                $mappedLanguage = $settings->mapLanguage((string)($translation['language'] ?? ''));
                if ($mappedLanguage === '' || !in_array(strtolower($mappedLanguage), $allowedExportLanguages, true)) {
                    continue;
                }

                $context = $translation['context'] ?? '';
                $isFormie = str_starts_with($context, 'formie.') || $context === 'formie';
                $typeLabel = $isFormie ? TranslationManager::getFormiePluginName() : 'Site';

                $row = [
                    'translationKey' => $translation['translationKey'] ?? '',
                    'translation' => $translation['translation'] ?? '',
                    'category' => $translation['category'] ?? 'messages',
                    'type' => $typeLabel,
                ];

                $row['context'] = $context;

                $row['status'] = $translation['status'] ?? '';
                $row['origin'] = $translation['translationOrigin'] ?? 'system';
                $row['language'] = $mappedLanguage;
                $row['createdBy'] = $this->resolveUserEmail($translation['createdByUserId'] ?? null, $userEmailMap);
                $row['reviewedBy'] = $this->resolveUserEmail($translation['reviewedByUserId'] ?? null, $userEmailMap);
                $row['reviewedAt'] = $translation['reviewedAt'] ?? '';
                $row['dateUpdated'] = $translation['dateUpdated'] ?? '';

                $rows[] = $row;
            }

            $filenameParts = ['export'];

            // Add language info to filename (sanitized to prevent header injection)
            if ($exportAll) {
                $filenameParts[] = 'all-languages';
            } elseif (!empty($languageParam)) {
                $filenameParts[] = $this->sanitizeFilenamePart($languageParam);
            }

            // Add category to filename (sanitized)
            if ($categoryParam && $categoryParam !== 'all') {
                $filenameParts[] = $this->sanitizeFilenamePart($categoryParam);
            }

            if ($typeParam && $typeParam !== 'all') {
                $filenameParts[] = $this->sanitizeFilenamePart($typeParam);
            }
            if ($statusParam && $statusParam !== 'all') {
                $filenameParts[] = $this->sanitizeFilenamePart($statusParam);
            }
            if ($originParam && $originParam !== 'all') {
                $filenameParts[] = $this->sanitizeFilenamePart($originParam);
            }

            $filename = ExportHelper::filename($settings, $filenameParts, 'csv');

            return ExportHelper::dispatchTable($rows, $headers, 'csv', $filename, ['reviewedAt', 'dateUpdated']);
        } catch (\Exception $e) {
            $this->logError('Export failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Export download action - works with regular CP URLs instead of action URLs
     *
     * @return Response
     */
    public function actionDownload(): Response
    {
        return $this->actionIndex();
    }
    
    /**
     * Export selected translations as CSV
     *
     * @return Response
     */
    public function actionSelected(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $ids = $request->getRequiredBodyParam('ids');

        if (!is_string($ids)) {
            throw new \Exception('Invalid IDs provided');
        }

        $ids = json_decode($ids, true);
        if (!is_array($ids)) {
            throw new \Exception('Invalid IDs format');
        }

        // Filter to valid integer IDs only
        $ids = array_filter($ids, fn($id) => is_numeric($id) && (int) $id > 0);
        $ids = array_map('intval', $ids);

        if (empty($ids)) {
            throw new \Exception('No valid IDs provided');
        }

        $settings = TranslationManager::$plugin->getSettings();
        $headers = [
            'Translation Key',
            'Translation',
            'Category',
            'Type',
            'Context',
            'Status',
            'Origin',
            'Language',
            'Created By',
            'Reviewed By',
            'Reviewed At',
            'Updated At',
        ];

        /** @var array<int,TranslationRecord> $translationsById */
        $translationsById = TranslationRecord::find()
            ->where(['id' => $ids])
            ->indexBy('id')
            ->all();

        $rows = [];
        $languages = [];
        $types = [];
        $userIds = [];

        foreach ($ids as $id) {
            if (!isset($translationsById[$id])) {
                continue;
            }
            $translation = $translationsById[$id];

            if (!empty($translation->createdByUserId)) {
                $userIds[] = (int) $translation->createdByUserId;
            }
            if (!empty($translation->reviewedByUserId)) {
                $userIds[] = (int) $translation->reviewedByUserId;
            }
        }

        $userEmailMap = $this->getUserEmailMap($userIds);

        foreach ($ids as $id) {
            if (!isset($translationsById[$id])) {
                continue;
            }
            $translation = $translationsById[$id];

            $context = $translation->context ?? '';
            $isFormie = str_starts_with($context, 'formie.') || $context === 'formie';
            $typeLabel = $isFormie ? TranslationManager::getFormiePluginName() : 'Site';

            $row = [
                'translationKey' => $translation->translationKey ?? '',
                'translation' => $translation->translation ?? '',
                'category' => $translation->category ?? 'messages',
                'type' => $typeLabel,
            ];

            $row['context'] = $context;

            $row['status'] = $translation->status ?? '';
            $row['origin'] = $translation->translationOrigin ?? 'system';
            $row['language'] = $settings->mapLanguage((string)($translation->language ?? ''));
            $row['createdBy'] = $this->resolveUserEmail($translation->createdByUserId ?? null, $userEmailMap);
            $row['reviewedBy'] = $this->resolveUserEmail($translation->reviewedByUserId ?? null, $userEmailMap);
            $row['reviewedAt'] = $translation->reviewedAt ?? '';
            $row['dateUpdated'] = $translation->dateUpdated ?? '';

            $rows[] = $row;
            if (!empty($translation->language)) {
                $languages[] = $translation->language;
            }
            $types[] = $isFormie ? 'formie' : 'site';
        }

        $languages = array_unique($languages);
        $types = array_unique($types);

        $filenameParts = ['export-selected'];

        if (count($languages) === 1 && !empty($languages[0])) {
            $filenameParts[] = $this->sanitizeFilenamePart($languages[0]);
        } else {
            $filenameParts[] = 'multi-language';
        }

        if (count($types) === 1) {
            $filenameParts[] = $this->sanitizeFilenamePart($types[0]);
        }

        $filename = ExportHelper::filename($settings, $filenameParts, 'csv');

        return ExportHelper::dispatchTable($rows, $headers, 'csv', $filename, ['reviewedAt', 'dateUpdated']);
    }


    /**
     * Sanitize a string for use in filename
     *
     * Prevents header injection and ensures valid filename characters.
     * Only allows alphanumeric, dots, underscores, and hyphens.
     */
    private function sanitizeFilenamePart(string $part): string
    {
        // Replace any non-alphanumeric characters (except ._-) with hyphen
        $sanitized = preg_replace('/[^a-z0-9._-]+/i', '-', $part);
        // Remove leading/trailing hyphens and convert to lowercase
        return strtolower(trim($sanitized, '-'));
    }

    /**
     * Build a map of userId => email for export metadata.
     *
     * @param array<int> $userIds
     * @return array<int,string>
     */
    private function getUserEmailMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id) => $id > 0)));
        if (empty($userIds)) {
            return [];
        }

        $users = User::find()
            ->status(null)
            ->id($userIds)
            ->all();

        $map = [];
        foreach ($users as $user) {
            if ($user->id) {
                $map[(int) $user->id] = (string) ($user->email ?? '');
            }
        }

        return $map;
    }

    /**
     * Resolve a user email from an ID and preloaded map.
     */
    private function resolveUserEmail(mixed $userId, array $map): string
    {
        $id = (int) $userId;
        if ($id <= 0) {
            return '';
        }

        return $map[$id] ?? '';
    }

    /**
     * Get canonical language codes allowed for export.
     *
     * Includes mapped target locales and canonical site locales.
     *
     * @return array<int,string>
     */
    private function getAllowedExportLanguages(): array
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
