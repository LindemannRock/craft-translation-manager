<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\helpers;

use craft\helpers\FileHelper;
use lindemannrock\translationmanager\services\IntegrationService;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Finds generated PHP translation files that are no longer current targets.
 *
 * @since 5.25.1
 */
class GeneratedFileCleanupHelper
{
    /**
     * @return array{files: array<int, array{path:string,language:string,category:string,reason:string}>, totalCandidates:int}
     */
    public static function getCandidates(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getGenerationPath();

        if (!is_dir($basePath)) {
            return [
                'files' => [],
                'totalCandidates' => 0,
            ];
        }

        $validLanguages = self::validLanguages();
        $validCategories = self::validCategories();
        $files = [];

        foreach (FileHelper::findFiles($basePath, ['only' => ['*.php']]) as $file) {
            $relativePath = str_replace('\\', '/', FileHelper::normalizePath(substr($file, strlen($basePath) + 1)));
            $language = dirname($relativePath);
            $category = basename($relativePath, '.php');

            if ($language === '.' || str_contains($language, '/')) {
                continue;
            }

            $invalidLanguage = !isset($validLanguages[strtolower($language)]);
            $invalidCategory = !isset($validCategories[$category]);

            if (!$invalidLanguage && !$invalidCategory) {
                continue;
            }

            $files[] = [
                'path' => $relativePath,
                'language' => $language,
                'category' => $category,
                'reason' => $invalidLanguage && $invalidCategory
                    ? 'language-category'
                    : ($invalidLanguage ? 'language' : 'category'),
            ];
        }

        usort($files, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));

        return [
            'files' => $files,
            'totalCandidates' => count($files),
        ];
    }

    public static function deleteCandidate(string $relativePath): bool
    {
        $candidatePaths = array_column(self::getCandidates()['files'], 'path');
        if (!in_array($relativePath, $candidatePaths, true)) {
            return false;
        }

        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getGenerationPath();
        $path = FileHelper::normalizePath($basePath . DIRECTORY_SEPARATOR . $relativePath);
        $realBasePath = realpath($basePath);
        $realPath = realpath($path);

        if ($realBasePath === false || $realPath === false || !str_starts_with($realPath, $realBasePath . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return @unlink($realPath);
    }

    /**
     * @return array<string,bool>
     */
    private static function validLanguages(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $languages = [];

        foreach (TranslationManager::getInstance()->getAllowedSites() as $site) {
            $languages[] = $settings->mapLanguage($site->language);
        }

        foreach ($settings->getActiveLocaleMapping() as $target) {
            $languages[] = $target;
        }

        return array_fill_keys(array_map('strtolower', array_unique(array_filter($languages))), true);
    }

    /**
     * @return array<string,bool>
     */
    private static function validCategories(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $categories = $settings->getEnabledCategories();

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        foreach ($integrationService->getEnabledIntegrations() as $integration) {
            $categories[] = $integration->getCategory();
        }

        return array_fill_keys(array_unique(array_filter($categories)), true);
    }
}
