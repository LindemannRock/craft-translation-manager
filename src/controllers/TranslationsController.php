<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Controller for managing translations in the Control Panel
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to access Translation Manager.'));
        }

        // For other actions, require manageTranslations permission
        if ($action->id !== 'index' && !$user->checkPermission('translationManager:manageTranslations')) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to view translations.'));
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
        $user = Craft::$app->getUser();

        // ---- Param parsing + allowlist validation ------------------------
        // Every parameter that controls filtering or sorting goes through an
        // explicit allowlist. Anything off-list snaps to the default — never
        // pass user input through to a query or template literal.

        $status = (string) $request->getParam('status', 'all');
        $validStatuses = ['all', 'pending', 'draft', 'translated', 'unused'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'all';
        }

        $type = (string) $request->getParam('type', 'all');
        $validTypes = ['all', 'forms', 'site'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'all';
        }

        $origin = (string) $request->getParam('origin', 'all');
        $validOrigins = ['all', 'ai', 'manual', 'import', 'system'];
        if (!in_array($origin, $validOrigins, true)) {
            $origin = 'all';
        }

        // Category allowlist depends on enabled categories at request time
        // (categories are operator-configured), so build it dynamically.
        $allCategories = $settings->getAllCategories();
        $category = (string) $request->getParam('category', 'all');
        $validCategories = array_merge(['all'], $allCategories);
        if (!in_array($category, $validCategories, true)) {
            $category = 'all';
        }

        // 64-char defensive clamp on free-text search.
        $search = trim((string) $request->getParam('search', ''));
        if (mb_strlen($search) > 64) {
            $search = mb_substr($search, 0, 64);
        }

        // Sort field allowlist mirrors the service's sortMap.
        $validSortFields = ['translationKey', 'translation', 'type', 'status', 'category'];
        $sort = (string) $request->getParam('sort', 'translationKey');
        if (!in_array($sort, $validSortFields, true)) {
            $sort = 'translationKey';
        }
        $dir = strtolower((string) $request->getParam('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        // Language selection — normalize to canonical mapped target.
        $language = (string) $request->getParam('language', '');
        if ($language === '') {
            $language = Craft::$app->getSites()->getCurrentSite()->language;
        }
        $language = $settings->mapLanguage($language);

        $uniqueLanguages = TranslationManager::getInstance()->getUniqueLanguages();

        // ---- Pagination ---------------------------------------------------
        $page = max(1, (int) $request->getParam('page', 1));
        $limit = max(1, (int) $settings->itemsPerPage);
        $offset = ($page - 1) * $limit;

        // ---- Load + filter (delegated to service) -------------------------
        $criteria = [
            'language' => $language,
            'status' => $status,
            'search' => $search,
            'sort' => $sort,
            'dir' => $dir,
            'type' => $type,
            'origin' => $origin,
            'category' => $category,
        ];

        $allTranslations = TranslationManager::getInstance()->translations->getTranslations($criteria);

        // totalCount is computed *after* filtering so the pager reflects what
        // the user can actually see, not the underlying table size.
        $totalCount = count($allTranslations);
        $totalPages = (int) ceil($totalCount / $limit);

        $translations = array_slice($allTranslations, $offset, $limit);
        $translations = $this->hydrateAuditFields($translations);

        $stats = TranslationManager::getInstance()->translations->getStatistics();

        // ---- Permission booleans (computed once, passed to template) -----
        $canEdit = $user->checkPermission('translationManager:editTranslations');
        $canApprove = $user->checkPermission('translationManager:approveTranslations');
        $canDelete = $user->checkPermission('translationManager:deleteTranslations');
        $canExport = $user->checkPermission('translationManager:exportTranslations');
        $canGenerateAll = $user->checkPermission('translationManager:generateAllTranslations');
        $canGenerateFormie = $user->checkPermission('translationManager:generateFormieTranslations');
        $canGenerateSite = $user->checkPermission('translationManager:generateSiteTranslations');
        $canGenerate = $user->checkPermission('translationManager:generateTranslations');
        $hasAnyGeneratePermission = $canGenerate || $canGenerateAll || $canGenerateFormie || $canGenerateSite;

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
            'currentLanguage' => $language,
            'uniqueLanguages' => $uniqueLanguages,
            // Legacy support (keeping for backwards compatibility)
            'allSites' => TranslationManager::getInstance()->getAllowedSites(),
            // Pre-computed permission booleans — avoid currentUser.can() in Twig.
            'canEdit' => $canEdit,
            'canApprove' => $canApprove,
            'canDelete' => $canDelete,
            'canExport' => $canExport,
            'canGenerateAll' => $canGenerateAll,
            'canGenerateFormie' => $canGenerateFormie,
            'canGenerateSite' => $canGenerateSite,
            'hasAnyGeneratePermission' => $hasAnyGeneratePermission,
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
            $formatted = DateFormatHelper::formatDatetime($dateValue);
            return $formatted ?? (string)$dateValue;
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to edit translations.'));
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
            TranslationManager::getInstance()->generate->triggerAutoGenerate();

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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to edit translations.'));
        }

        $request = Craft::$app->getRequest();
        $translations = $request->getRequiredBodyParam('translations');
        
        $saved = 0;

        // Pre-fetch every referenced row in a single SELECT, indexed by id.
        // Same shape as ExportController::actionSelected (audit 2.8) — replaces
        // the previous per-row findOne() loop (audit 3.2).
        $validIds = [];
        foreach ($translations as $data) {
            if (isset($data['id']) && is_numeric($data['id'])) {
                $validIds[] = (int) $data['id'];
            }
        }

        /** @var array<int, TranslationRecord> $records */
        $records = $validIds
            ? TranslationRecord::find()->where(['id' => $validIds])->indexBy('id')->all()
            : [];

        foreach ($translations as $data) {
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                continue;
            }

            $translation = $records[(int) $data['id']] ?? null;
            if (!$translation instanceof TranslationRecord) {
                continue;
            }

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
            }
        }

        if ($saved > 0) {
            TranslationManager::getInstance()->generate->triggerAutoGenerate();
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to delete translations.'));
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
     *
     * @since 5.22.0
     */
    public function actionSetStatus(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!Craft::$app->getUser()->checkPermission('translationManager:editTranslations')) {
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to edit translations.'));
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to approve translations.'));
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

        // Re-generate files when rows are marked translated (autoGenerate gated inside).
        if ($updated > 0 && $targetStatus === 'translated') {
            TranslationManager::getInstance()->generate->triggerAutoGenerate();
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
