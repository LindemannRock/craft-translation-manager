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
use lindemannrock\translationmanager\helpers\FeatureGate;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\services\IntegrationService;
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
            throw new ForbiddenHttpException(Craft::t('translation-manager', 'User does not have permission to export translations.'));
        }

        return parent::beforeAction($action);
    }

    /**
     * Export translations.
     *
     * The translations table posts an explicit format from the base export menu.
     * The Import/Export page intentionally posts no format and remains CSV-only
     * through the default below.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        try {
            $request = Craft::$app->getRequest();
            $settings = TranslationManager::$plugin->getSettings();
            $format = (string)($request->getBodyParam('format') ?? $request->getParam('format', 'csv'));
            ExportHelper::assertFormatEnabled($format, 'translation-manager');

            $ids = $this->parseIds($request->getBodyParam('ids'));
            $export = $ids ? $this->buildSelectedExport($ids) : $this->buildFilteredExport();
            $extension = ExportHelper::extensionForFormat($format);
            $filename = ExportHelper::filename($settings, $export['filenameParts'], $extension);

            return ExportHelper::dispatchTable(
                rows: $export['rows'],
                headers: $export['headers'],
                format: $format,
                filename: $filename,
                dateColumns: $export['dateColumns'],
                excelOptions: [
                    'sheetTitle' => Craft::t('translation-manager', 'Translations'),
                ],
            );
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
        $settings = TranslationManager::$plugin->getSettings();
        $format = (string)$request->getBodyParam('format', 'csv');
        ExportHelper::assertFormatEnabled($format, 'translation-manager');
        $ids = $this->parseIds($ids);

        if (!$ids) {
            throw new \Exception('No valid IDs provided');
        }

        $export = $this->buildSelectedExport($ids);
        $extension = ExportHelper::extensionForFormat($format);
        $filename = ExportHelper::filename($settings, $export['filenameParts'], $extension);

        return ExportHelper::dispatchTable(
            rows: $export['rows'],
            headers: $export['headers'],
            format: $format,
            filename: $filename,
            dateColumns: $export['dateColumns'],
            excelOptions: [
                'sheetTitle' => Craft::t('translation-manager', 'Translations'),
            ],
        );
    }

    /**
     * @return array<int,string>
     */
    private function exportHeaders(): array
    {
        return [
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
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, headers: array<int,string>, filenameParts: array<int,string>, dateColumns: array<int,string>}
     */
    private function buildFilteredExport(): array
    {
        $request = Craft::$app->getRequest();
        $settings = TranslationManager::$plugin->getSettings();
        $translationsService = TranslationManager::getInstance()->translations;

        $criteria = [];
        $languageParam = $request->getParam('language') ?: $request->getBodyParam('language');
        $exportAll = empty($languageParam);

        if (!empty($languageParam)) {
            $languageParam = $settings->mapLanguage((string)$languageParam);
            $criteria['language'] = $languageParam;
        } else {
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

        $categoryParam = $request->getParam('category') ?: $request->getBodyParam('category');
        if ($categoryParam && $categoryParam !== 'all') {
            $integration = $this->getIntegrationService()->getIntegrationForCategory((string)$categoryParam);
            if ($integration !== null) {
                $criteria['type'] = $integration->getSourceType();
                $criteria['category'] = $categoryParam;
            } else {
                $criteria['type'] = 'site';
                $criteria['category'] = $categoryParam;
            }
        }

        $translations = $translationsService->getTranslations($criteria);
        $rows = $this->buildRows($translations, true);
        $filenameParts = ['export'];

        if ($exportAll) {
            $filenameParts[] = 'all-languages';
        } elseif (!empty($languageParam)) {
            $filenameParts[] = (string)$languageParam;
        }

        if ($categoryParam && $categoryParam !== 'all') {
            $filenameParts[] = (string)$categoryParam;
        }
        if ($typeParam && $typeParam !== 'all') {
            $filenameParts[] = (string)$typeParam;
        }
        if ($statusParam && $statusParam !== 'all') {
            $filenameParts[] = (string)$statusParam;
        }
        if ($originParam && $originParam !== 'all') {
            $filenameParts[] = (string)$originParam;
        }

        return $this->exportPayload($rows, $filenameParts);
    }

    /**
     * @param array<int> $ids
     * @return array{rows: array<int,array<string,mixed>>, headers: array<int,string>, filenameParts: array<int,string>, dateColumns: array<int,string>}
     */
    private function buildSelectedExport(array $ids): array
    {
        /** @var array<int,TranslationRecord> $translationsById */
        $translationsById = TranslationRecord::find()
            ->where(['id' => $ids])
            ->indexBy('id')
            ->all();

        $translations = [];
        foreach ($ids as $id) {
            if (isset($translationsById[$id])) {
                $translations[] = $translationsById[$id];
            }
        }

        $rows = $this->buildRows($translations, false);
        $languages = [];
        $types = [];

        foreach ($translations as $translation) {
            if (!empty($translation->language)) {
                $languages[] = $translation->language;
            }

            $context = $translation->context ?? '';
            $integration = $this->getIntegrationService()->getIntegrationForContext($context);
            $types[] = $integration?->getName() ?? 'site';
        }

        $languages = array_values(array_unique($languages));
        $types = array_values(array_unique($types));

        $filenameParts = ['export-selected'];

        if (count($languages) === 1 && !empty($languages[0])) {
            $filenameParts[] = (string)$languages[0];
        } else {
            $filenameParts[] = 'multi-language';
        }

        if (count($types) === 1) {
            $filenameParts[] = (string)$types[0];
        }

        return $this->exportPayload($rows, $filenameParts);
    }

    /**
     * @param array<int,array<string,mixed>|TranslationRecord> $translations
     * @return array<int,array<string,mixed>>
     */
    private function buildRows(array $translations, bool $filterAllowedLanguages): array
    {
        $settings = TranslationManager::$plugin->getSettings();
        $allowedExportLanguages = $filterAllowedLanguages ? $this->getAllowedExportLanguages() : [];
        $userIds = [];

        foreach ($translations as $translation) {
            $createdByUserId = $this->translationValue($translation, 'createdByUserId');
            $reviewedByUserId = $this->translationValue($translation, 'reviewedByUserId');
            if (!empty($createdByUserId)) {
                $userIds[] = (int)$createdByUserId;
            }
            if (!empty($reviewedByUserId)) {
                $userIds[] = (int)$reviewedByUserId;
            }
        }

        $userEmailMap = $this->getUserEmailMap($userIds);
        $rows = [];

        foreach ($translations as $translation) {
            $mappedLanguage = $settings->mapLanguage((string)$this->translationValue($translation, 'language', ''));
            if (
                $filterAllowedLanguages
                && ($mappedLanguage === '' || !in_array(strtolower($mappedLanguage), $allowedExportLanguages, true))
            ) {
                continue;
            }

            $context = (string)$this->translationValue($translation, 'context', '');
            $typeLabel = $this->getTypeLabelForContext($context);

            $row = [
                'translationKey' => $this->translationValue($translation, 'translationKey', ''),
                'translation' => $this->translationValue($translation, 'translation', ''),
                'category' => $this->translationValue($translation, 'category', 'messages'),
                'type' => $typeLabel,
            ];

            $row['context'] = $context;
            $row['status'] = $this->translationValue($translation, 'status', '');
            $row['origin'] = $this->exportOrigin((string)$this->translationValue($translation, 'translationOrigin', 'system'));
            $row['language'] = $mappedLanguage;
            $row['createdBy'] = $this->resolveUserEmail($this->translationValue($translation, 'createdByUserId'), $userEmailMap);
            $row['reviewedBy'] = $this->resolveUserEmail($this->translationValue($translation, 'reviewedByUserId'), $userEmailMap);
            $row['reviewedAt'] = $this->translationValue($translation, 'reviewedAt', '');
            $row['dateUpdated'] = $this->translationValue($translation, 'dateUpdated', '');

            $rows[] = $row;
        }

        return $rows;
    }

    private function exportOrigin(string $origin): string
    {
        if ($origin === 'ai' && !FeatureGate::aiTranslationsEnabled()) {
            return 'system';
        }

        return $origin;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $filenameParts
     * @return array{rows: array<int,array<string,mixed>>, headers: array<int,string>, filenameParts: array<int,string>, dateColumns: array<int,string>}
     */
    private function exportPayload(array $rows, array $filenameParts): array
    {
        return [
            'rows' => $rows,
            'headers' => $this->exportHeaders(),
            'filenameParts' => $filenameParts,
            'dateColumns' => ['reviewedAt', 'dateUpdated'],
        ];
    }

    /**
     * @return array<int>
     */
    private function parseIds(mixed $ids): array
    {
        if ($ids === null || $ids === '') {
            return [];
        }

        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        if (!is_array($ids)) {
            return [];
        }

        $ids = array_filter($ids, fn($id) => is_numeric($id) && (int)$id > 0);

        return array_values(array_map('intval', $ids));
    }

    private function translationValue(array|TranslationRecord $translation, string $key, mixed $default = null): mixed
    {
        if (is_array($translation)) {
            return $translation[$key] ?? $default;
        }

        return $translation->{$key} ?? $default;
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

    private function getIntegrationService(): IntegrationService
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');

        return $integrationService;
    }

    private function getTypeLabelForContext(string $context): string
    {
        $integration = $this->getIntegrationService()->getIntegrationForContext($context);
        if ($integration === null) {
            return 'Site';
        }

        if ($integration->getName() === 'formie') {
            return TranslationManager::getFormiePluginName();
        }

        return ucfirst($integration->getName());
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
