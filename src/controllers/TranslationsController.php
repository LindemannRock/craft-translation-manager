<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing translations in the Control Panel
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025 LindemannRock
 */

namespace lindemannrock\translationmanager\controllers;

use Craft;
use craft\elements\User;
use craft\helpers\Db;
use craft\web\Controller;
use lindemannrock\base\helpers\CpNavHelper;
use lindemannrock\base\helpers\DateFormatHelper;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Translations Controller
 *
 * @since 1.0.0
 */
class TranslationsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $user = Craft::$app->getUser();

        // For index action, redirect to first accessible section if no manageTranslations permission
        if ($action->id === 'index' && !$user->checkPermission('translationManager:manageTranslations')) {
            $settings = TranslationManager::getInstance()->getSettings();
            $sections = TranslationManager::getInstance()->getCpSections($settings, false, true);
            $route = CpNavHelper::firstAccessibleRoute($user, $settings, $sections);
            if ($route) {
                Craft::$app->getResponse()->redirect($route)->send();
                return false;
            }

            // No access at all
            throw new ForbiddenHttpException('User does not have permission to access Translation Manager');
        }

        // For other actions, require manageTranslations permission
        if ($action->id !== 'index' && !$user->checkPermission('translationManager:manageTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to view translations');
        }

        return parent::beforeAction($action);
    }

    /**
     * Translation index page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        $request = Craft::$app->getRequest();
        $settings = TranslationManager::getInstance()->getSettings();
        
        // Get query parameters
        $page = (int) $request->getParam('page', 1);
        $status = $request->getParam('status', 'all');
        $search = $request->getParam('search', '');
        $sort = $request->getParam('sort', 'translationKey');

        // Get language selection
        $language = $request->getParam('language');

        if (!$language) {
            // Default to current site's language
            $language = Craft::$app->getSites()->getCurrentSite()->language;
        }

        // Always normalize selected language to canonical mapped target.
        $language = $settings->mapLanguage($language);

        // Get unique languages for the selector
        $uniqueLanguages = TranslationManager::getInstance()->getUniqueLanguages();
        $dir = $request->getParam('dir', 'asc');
        $type = $request->getParam('type', 'all');
        $origin = $request->getParam('origin', 'all');
        $category = $request->getParam('category', 'all');

        $limit = $settings->itemsPerPage;
        $offset = ($page - 1) * $limit;

        // Get translations with filters
        $criteria = [
            'language' => $language,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'type' => $type,
            'origin' => $origin,
            'category' => $category,
            'includeUsageCheck' => true,
        ];

        $allTranslations = TranslationManager::getInstance()->translations->getTranslations($criteria);

        // Calculate pagination
        $totalCount = count($allTranslations);
        $totalPages = ceil($totalCount / $limit);

        // Slice for current page
        $translations = array_slice($allTranslations, $offset, $limit);
        $translations = $this->hydrateAuditFields($translations);

        // Get statistics
        $stats = TranslationManager::getInstance()->translations->getStatistics();

        // Get all available categories for the filter dropdown
        $allCategories = $settings->getAllCategories();

        return $this->renderTemplate('translation-manager/translations/index', [
            'translations' => $translations,
            'stats' => $stats,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'type' => $type,
            'origin' => $origin,
            'category' => $category,
            'allCategories' => $allCategories,
            'settings' => $settings,
            // Language-based variables
            'currentLanguage' => $language,
            'uniqueLanguages' => $uniqueLanguages,
            // Legacy support (keeping for backwards compatibility)
            'allSites' => TranslationManager::getInstance()->getAllowedSites(),
        ]);
    }

    /**
     * Add UI-ready audit fields to translation rows.
     *
     * @param array<int, array<string, mixed>> $translations
     * @return array<int, array<string, mixed>>
     */
    private function hydrateAuditFields(array $translations): array
    {
        $userIds = [];
        foreach ($translations as $translation) {
            if (!empty($translation['createdByUserId'])) {
                $userIds[] = (int)$translation['createdByUserId'];
            }
            if (!empty($translation['reviewedByUserId'])) {
                $userIds[] = (int)$translation['reviewedByUserId'];
            }
        }

        $userEmailMap = $this->getUserEmailMap($userIds);

        foreach ($translations as &$translation) {
            $translation['createdByEmail'] = $this->resolveUserEmail($translation['createdByUserId'] ?? null, $userEmailMap);
            $translation['reviewedByEmail'] = $this->resolveUserEmail($translation['reviewedByUserId'] ?? null, $userEmailMap);
            $translation['reviewedAtFormatted'] = $this->formatDateForDisplay($translation['reviewedAt'] ?? null);
        }
        unset($translation);

        return $translations;
    }

    /**
     * @param array<int> $userIds
     * @return array<int, string>
     */
    private function getUserEmailMap(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($userIds)) {
            return [];
        }

        $users = User::find()
            ->status(null)
            ->id($userIds)
            ->all();

        $map = [];
        foreach ($users as $user) {
            $map[(int)$user->id] = (string)($user->email ?? '');
        }

        return $map;
    }

    /**
     * @param mixed $userId
     * @param array<int, string> $userEmailMap
     */
    private function resolveUserEmail(mixed $userId, array $userEmailMap): string
    {
        $id = (int)$userId;
        if ($id <= 0) {
            return '';
        }

        return $userEmailMap[$id] ?? (string)$id;
    }

    private function formatDateForDisplay(mixed $dateValue): string
    {
        if (!$dateValue) {
            return '';
        }

        try {
            $date = $dateValue instanceof \DateTime
                ? $dateValue
                : new \DateTime((string)$dateValue);
            return DateFormatHelper::formatDatetime($date);
        } catch (\Throwable) {
            return (string)$dateValue;
        }
    }

    /**
     * Save a single translation
     *
     * @return Response
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to edit translations');
        }

        $request = Craft::$app->getRequest();
        $id = $request->getRequiredBodyParam('id');
        $translationText = $request->getBodyParam('translation', '');

        $translation = TranslationRecord::findOne($id);
        
        if (!$translation) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Translation not found'),
            ]);
        }

        $translation->translation = $translationText;
        $translation->status = $this->resolveStatusForSave($translation, $translationText);
        $translation->translationOrigin = 'manual';
        $translation->createdByUserId = Craft::$app->getUser()->getId();
        if ($translation->status === 'draft' || $translationText === '') {
            $translation->reviewedByUserId = null;
            $translation->reviewedAt = null;
        } else {
            $translation->reviewedByUserId = Craft::$app->getUser()->getId();
            $translation->reviewedAt = Db::prepareDateForDb(new \DateTime());
        }
        
        if (TranslationManager::getInstance()->translations->saveTranslation($translation)) {
            // Generate files if auto-generate is enabled
            if (TranslationManager::getInstance()->getSettings()->autoGenerate) {
                $isFormie = str_starts_with($translation->context, 'formie.');
                if ($isFormie) {
                    TranslationManager::getInstance()->export->exportFormieTranslations();
                } else {
                    TranslationManager::getInstance()->export->exportSiteTranslations();
                }
            }

            return $this->asJson([
                'success' => true,
                'message' => Craft::t('translation-manager', 'Translation saved'),
                'status' => $translation->status,
            ]);
        }

        return $this->asJson([
            'success' => false,
            'error' => Craft::t('translation-manager', 'Failed to save translation'),
        ]);
    }

    /**
     * Save all translations
     *
     * @return Response
     */
    public function actionSaveAll(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to edit translations');
        }

        $request = Craft::$app->getRequest();
        $translations = $request->getRequiredBodyParam('translations');
        
        $saved = 0;
        $hasFormie = false;
        $hasSite = false;

        foreach ($translations as $data) {
            // Validate ID is numeric
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                continue;
            }

            $translation = TranslationRecord::findOne($data['id']);
            if ($translation) {
                $translationText = $data['translation'] ?? '';

                // Validate input length to prevent DoS
                if (strlen($translationText) > 5000) {
                    continue;
                }

                $translation->translation = $translationText;
                $translation->status = $this->resolveStatusForSave($translation, $translationText);
                $translation->translationOrigin = 'manual';
                $translation->createdByUserId = Craft::$app->getUser()->getId();
                if ($translation->status === 'draft' || $translationText === '') {
                    $translation->reviewedByUserId = null;
                    $translation->reviewedAt = null;
                } else {
                    $translation->reviewedByUserId = Craft::$app->getUser()->getId();
                    $translation->reviewedAt = Db::prepareDateForDb(new \DateTime());
                }
                if (TranslationManager::getInstance()->translations->saveTranslation($translation)) {
                    $saved++;
                    
                    // Track what types we're saving
                    if (str_starts_with($translation->context, 'formie.')) {
                        $hasFormie = true;
                    } else {
                        $hasSite = true;
                    }
                }
            }
        }

        // Generate files if auto-generate is enabled
        if (TranslationManager::getInstance()->getSettings()->autoGenerate && $saved > 0) {
            if ($hasFormie) {
                TranslationManager::getInstance()->export->exportFormieTranslations();
            }
            if ($hasSite) {
                TranslationManager::getInstance()->export->exportSiteTranslations();
            }
        }

        return $this->asJson([
            'success' => true,
            'saved' => $saved,
            // Only suggest refresh if we've exported files (which might change contexts)
            'shouldRefresh' => false, // Set to true if you want full page refresh
        ]);
    }

    /**
     * Delete translations
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        
        // Check permission
        if (!Craft::$app->getUser()->checkPermission('translationManager:deleteTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to delete translations');
        }

        $request = Craft::$app->getRequest();
        $ids = $request->getRequiredBodyParam('ids');
        
        if (!is_array($ids)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid IDs provided'),
            ]);
        }

        $deleted = TranslationManager::getInstance()->translations->deleteTranslations($ids);

        return $this->asJson([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Bulk set status for selected translations.
     */
    public function actionSetStatus(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException('User does not have permission to edit translations');
        }

        $request = Craft::$app->getRequest();
        $ids = $request->getRequiredBodyParam('ids');
        $targetStatus = (string) $request->getRequiredBodyParam('status');

        if (!is_array($ids) || !in_array($targetStatus, ['draft', 'translated'], true)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('translation-manager', 'Invalid request payload.'),
            ]);
        }

        $settings = TranslationManager::getInstance()->getSettings();
        if (
            $targetStatus === 'translated'
            && $settings->requireApproval
            && !Craft::$app->getUser()->checkPermission('translationManager:approveTranslations')
        ) {
            throw new ForbiddenHttpException('User does not have permission to approve translations');
        }

        $updated = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                $skipped++;
                continue;
            }

            $translation = TranslationRecord::findOne((int) $id);
            if (!$translation instanceof TranslationRecord) {
                $skipped++;
                continue;
            }

            if ($translation->status === 'unused') {
                $skipped++;
                continue;
            }

            $translation->status = $targetStatus;
            $translation->translationOrigin = 'manual';
            $translation->createdByUserId = Craft::$app->getUser()->getId();
            $translation->dateUpdated = Db::prepareDateForDb(new \DateTime());

            if ($targetStatus === 'translated') {
                $translation->reviewedByUserId = Craft::$app->getUser()->getId();
                $translation->reviewedAt = Db::prepareDateForDb(new \DateTime());
            } else {
                $translation->reviewedByUserId = null;
                $translation->reviewedAt = null;
            }

            if ($translation->save()) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        // Generate files if auto-generate is enabled and rows were marked translated.
        if ($updated > 0 && $targetStatus === 'translated' && $settings->autoGenerate) {
            TranslationManager::getInstance()->export->exportAll();
        }

        return $this->asJson([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Resolve target status on manual save based on workflow settings + permissions.
     */
    private function resolveStatusForSave(TranslationRecord $translation, string $translationText): string
    {
        if ($translation->status === 'unused') {
            return 'unused';
        }

        if (trim($translationText) === '') {
            return 'pending';
        }

        $settings = TranslationManager::getInstance()->getSettings();
        if (!$settings->requireApproval) {
            return 'translated';
        }

        $canApprove = Craft::$app->getUser()->checkPermission('translationManager:approveTranslations');
        return $canApprove ? 'translated' : 'draft';
    }
}
