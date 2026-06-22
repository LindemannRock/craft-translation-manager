<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Service for generating PHP translation files that Craft CMS uses for
 * multi-language support
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2025-2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use craft\base\Component;
use craft\helpers\FileHelper;
use lindemannrock\logginglibrary\traits\LoggingTrait;
use lindemannrock\translationmanager\interfaces\TranslationIntegrationInterface;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Generation Service
 *
 * @since 1.0.0
 */
class GenerationService extends Component
{
    use LoggingTrait;

    /**
     * Get the generation language for a site (applies locale mapping)
     */
    private function getGenerationLanguage(\craft\models\Site $site): string
    {
        $settings = TranslationManager::getInstance()->getSettings();
        return $settings->mapLanguage($site->language);
    }

    /**
     * Run generation iff the autoGenerate setting is enabled.
     *
     * Single funnel for the "regenerate files automatically on save/import"
     * behavior. Callers don't gate themselves on the setting — they always
     * call this method, and the gate decision lives in one place. Passing
     * source IDs limits generation to the changed sources; omitting them keeps
     * the legacy "regenerate everything" behavior for imports and broad jobs.
     *
     * @param string[]|null $sourceIds
     * @return bool true if generation ran, false if the setting is off
     * @since 5.24.0
     */
    public function triggerAutoGenerate(?array $sourceIds = null): bool
    {
        if (!TranslationManager::getInstance()->getSettings()->autoGenerate) {
            return false;
        }

        if ($sourceIds === null) {
            $this->generateAll();
            return true;
        }

        $this->generateSources($sourceIds);
        return true;
    }

    /**
     * @param string[] $sourceIds
     * @return array<string,array<string,mixed>>
     */
    public function generateSources(array $sourceIds): array
    {
        /** @var SourceService $sourceService */
        $sourceService = TranslationManager::getInstance()->get('sources');

        $results = [];
        foreach (array_values(array_unique(array_filter($sourceIds))) as $sourceId) {
            $source = $sourceService->getSourceById((string)$sourceId);
            if ($source === null) {
                continue;
            }

            $results[$source->id] = $source->providerName !== null
                ? $this->generateProviderTranslations($source->providerName)
                : $this->generateCategoryTranslations($source->category);
        }

        return $results;
    }

    /**
     * Generate all translation files (enabled integrations + site).
     *
     * @return array<string,mixed>
     */
    public function generateAll(): array
    {
        $this->logInfo('Starting generateAll()');
        $results = [
            'success' => true,
            'translationCount' => 0,
            'writtenFileCount' => 0,
            'deletedFileCount' => 0,
            'results' => [],
        ];

        $this->logInfo('About to generate integration translations');
        $integrationResults = $this->generateIntegrationTranslations();
        foreach ($integrationResults as $provider => $result) {
            $results['results'][$provider] = $result;
            $results['success'] = $results['success'] && (bool)($result['success'] ?? false);
            $results['translationCount'] += (int)($result['translationCount'] ?? 0);
            $results['writtenFileCount'] += (int)($result['writtenFileCount'] ?? 0);
            $results['deletedFileCount'] += (int)($result['deletedFileCount'] ?? 0);
        }
        $this->logInfo('Integration generation results', ['results' => $integrationResults]);

        $this->logInfo('About to generate site translations');
        $siteResult = $this->generateSiteTranslations();
        $results['results']['site'] = $siteResult;
        $results['success'] = $results['success'] && (bool)($siteResult['success'] ?? false);
        $results['translationCount'] += (int)($siteResult['translationCount'] ?? 0);
        $results['writtenFileCount'] += (int)($siteResult['writtenFileCount'] ?? 0);
        $results['deletedFileCount'] += (int)($siteResult['deletedFileCount'] ?? 0);
        $this->logInfo('Site generation result', ['result' => $siteResult]);

        $this->logInfo('generateAll() completed');
        return $results;
    }

    /**
     * Generate translation files for every enabled integration.
     *
     * @return array<string,array<string,mixed>> Results keyed by integration name
     */
    public function generateIntegrationTranslations(?string $sourceType = null): array
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integrations = $sourceType === null
            ? $integrationService->getEnabledIntegrations()
            : $integrationService->getIntegrationsBySourceType($sourceType, true);

        $results = [];
        foreach ($integrations as $integration) {
            $results[$integration->getName()] = $this->generateProviderTranslations($integration->getName());
        }

        return $results;
    }

    /**
     * Generate translation files for one integration provider.
     *
     * @return array<string,mixed>
     */
    public function generateProviderTranslations(string $provider): array
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integration = $integrationService->get($provider);

        if (!$integration instanceof TranslationIntegrationInterface) {
            $this->logInfo('Integration provider is not registered', ['provider' => $provider]);
            return $this->generationResult('provider', ucfirst($provider), [], false, $provider, [
                'Integration provider is not registered.',
            ]);
        }

        $category = $integration->getCategory();

        $this->logInfo('Starting integration translation generation', [
            'provider' => $provider,
            'category' => $category,
        ]);

        return $this->generateScope(
            [
                'type' => $integration->getSourceType(),
                'category' => $category,
                'status' => 'translated',
                'allSites' => true,
            ],
            [$category],
            'provider',
            ucfirst($provider),
            $provider,
        );
    }

    /**
     * Generate site translation files (per category).
     *
     * @return array<string,mixed>
     */
    public function generateSiteTranslations(): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $categories = $settings->getEnabledCategories();

        $this->logInfo('Starting site translation generation', ['categories' => $categories]);

        return $this->generateScope(
            [
                'type' => 'site',
                'status' => 'translated',
                'allSites' => true,
            ],
            $categories,
            'site',
            'Site',
        );
    }

    /**
     * Generate a single category's translation files
     *
     * @return array<string,mixed>
     * @since 5.0.0
     */
    public function generateCategoryTranslations(string $category): array
    {
        $this->logInfo('Starting single category translation generation', ['category' => $category]);

        return $this->generateScope(
            [
                'type' => 'site',
                'category' => $category,
                'status' => 'translated',
                'allSites' => true,
            ],
            [$category],
            'category',
            ucfirst($category),
        );
    }

    /**
     * @param array<string,mixed> $criteria
     * @param string[] $categories
     * @return array<string,mixed>
     */
    private function generateScope(
        array $criteria,
        array $categories,
        string $type,
        string $label,
        ?string $provider = null,
    ): array {
        $translations = TranslationManager::getInstance()->translations->getTranslations($criteria);

        $this->logInfo('Found translations to generate', [
            'type' => $type,
            'provider' => $provider,
            'categories' => $categories,
            'count' => count($translations),
        ]);

        $writeResult = $this->writeCategoryLanguageFiles($translations, $categories);

        return $this->generationResult(
            $type,
            $label,
            $categories,
            true,
            $provider,
            [],
            count($translations),
            $writeResult['writtenFileCount'],
            $writeResult['deletedFileCount'],
        );
    }

    /**
     * Group translated rows by category and mapped language, write the matching
     * PHP files, and delete stale files for categories with no generated output.
     *
     * @param array<int,array<string,mixed>> $translations Rows from getTranslations()
     * @param string[] $categories Categories in scope for stale-file cleanup
     * @return array{writtenFileCount:int,deletedFileCount:int}
     */
    private function writeCategoryLanguageFiles(array $translations, array $categories): array
    {
        $settings = TranslationManager::getInstance()->getSettings();
        $basePath = $settings->getGenerationPath();
        $sites = TranslationManager::getInstance()->getAllowedSites();
        $writtenFileCount = 0;
        $deletedFileCount = 0;

        // Group by category, then by MAPPED language (not siteId) so rows saved
        // under a mapped language (e.g. 'en') generate correctly and sites that
        // share a language merge into a single file.
        $translationsByCategoryAndLanguage = [];

        foreach ($translations as $translation) {
            $category = $translation['category'] ?? $settings->getPrimaryCategory();
            $language = $translation['language'] ?? 'en';
            $mappedLanguage = $settings->mapLanguage($language);

            if (!isset($translationsByCategoryAndLanguage[$category][$mappedLanguage])) {
                $translationsByCategoryAndLanguage[$category][$mappedLanguage] = [];
            }

            // Only include if there's a translation (not empty)
            if (!empty($translation['translation'])) {
                $translationsByCategoryAndLanguage[$category][$mappedLanguage][$translation['translationKey']] = $translation['translation'];
            }
        }

        // Write one file per category and language.
        foreach ($translationsByCategoryAndLanguage as $category => $translationsByLanguage) {
            foreach ($translationsByLanguage as $generationLanguage => $langTranslations) {
                $file = $this->resolveGeneratedFilePath($basePath, (string)$generationLanguage, (string)$category, true);
                $this->writeTranslationFile($file, $langTranslations, (string)$generationLanguage);
                $writtenFileCount++;
                $this->logInfo('Generated translation file', [
                    'count' => count($langTranslations),
                    'language' => $generationLanguage,
                    'category' => $category,
                ]);
            }
        }

        // Delete stale files for in-scope categories that produced no output.
        foreach ($categories as $category) {
            if (isset($translationsByCategoryAndLanguage[$category])) {
                continue;
            }

            foreach ($sites as $site) {
                $generationLanguage = $this->getGenerationLanguage($site);
                $file = $this->resolveGeneratedFilePath($basePath, $generationLanguage, (string)$category, false);
                if ($file !== null && file_exists($file)) {
                    @unlink($file);
                    $deletedFileCount++;
                    $this->logInfo('Deleted stale generated file', ['file' => $file, 'category' => $category]);
                }
            }
        }

        return [
            'writtenFileCount' => $writtenFileCount,
            'deletedFileCount' => $deletedFileCount,
        ];
    }

    private function resolveGeneratedFilePath(string $basePath, string $generationLanguage, string $category, bool $createDirectory): ?string
    {
        if ($createDirectory) {
            FileHelper::createDirectory($basePath);
        }

        $realBasePath = realpath($basePath);
        if ($realBasePath === false) {
            if ($createDirectory) {
                throw new \RuntimeException("Generation path does not exist: {$basePath}");
            }

            return null;
        }

        $realBasePath = FileHelper::normalizePath($realBasePath);
        $targetDirectory = FileHelper::normalizePath($realBasePath . DIRECTORY_SEPARATOR . $generationLanguage);

        if (!$this->isPathInside($targetDirectory, $realBasePath)) {
            throw new \RuntimeException('Generated translation directory resolved outside the generation path.');
        }

        if ($createDirectory) {
            FileHelper::createDirectory($targetDirectory);
        }

        $realTargetDirectory = realpath($targetDirectory);
        if ($realTargetDirectory === false) {
            if ($createDirectory) {
                throw new \RuntimeException("Generated translation directory does not exist: {$targetDirectory}");
            }

            return null;
        }

        $realTargetDirectory = FileHelper::normalizePath($realTargetDirectory);
        if (!$this->isPathInside($realTargetDirectory, $realBasePath)) {
            throw new \RuntimeException('Generated translation directory resolved outside the generation path.');
        }

        $targetFile = FileHelper::normalizePath($realTargetDirectory . DIRECTORY_SEPARATOR . $category . '.php');
        if (!$this->isPathInside($targetFile, $realTargetDirectory)) {
            throw new \RuntimeException('Generated translation file resolved outside its language directory.');
        }

        $realTargetFile = realpath($targetFile);
        if ($realTargetFile !== false && !$this->isPathInside(FileHelper::normalizePath($realTargetFile), $realBasePath)) {
            throw new \RuntimeException('Generated translation file resolved outside the generation path.');
        }

        return $targetFile;
    }

    private function isPathInside(string $path, string $basePath): bool
    {
        $path = FileHelper::normalizePath($path);
        $basePath = rtrim(FileHelper::normalizePath($basePath), '/\\');

        return $path !== $basePath && str_starts_with($path, $basePath . DIRECTORY_SEPARATOR);
    }

    /**
     * @param string[] $categories
     * @param string[] $warnings
     * @return array<string,mixed>
     */
    private function generationResult(
        string $type,
        string $label,
        array $categories,
        bool $success,
        ?string $provider = null,
        array $warnings = [],
        int $translationCount = 0,
        int $writtenFileCount = 0,
        int $deletedFileCount = 0,
    ): array {
        return [
            'success' => $success,
            'type' => $type,
            'provider' => $provider,
            'label' => $label,
            'categories' => $categories,
            'translationCount' => $translationCount,
            'writtenFileCount' => $writtenFileCount,
            'deletedFileCount' => $deletedFileCount,
            'warnings' => $warnings,
        ];
    }

    /**
     * Write translation file
     */
    private function writeTranslationFile(string $path, array $translations, string $language): void
    {
        $this->logInfo("Writing translation file", [
            'language' => $language,
            'path' => $path,
            'count' => count($translations),
        ]);

        $content = "<?php\n/**\n * {$language} translations\n * Auto-generated: " . date('Y-m-d H:i:s') . "\n */\nreturn [\n";

        foreach ($translations as $key => $value) {
            // Force keys to always be strings (especially important for numeric strings)
            // to prevent PHP from treating them as integer keys
            $exportedKey = var_export((string)$key, true);
            $exportedValue = var_export($value, true);
            $content .= "    {$exportedKey} => {$exportedValue},\n";
        }

        $content .= "];\n";

        // Secure file writing with atomic operation
        $tempFile = $path . '.tmp';

        if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
            $this->logError("Failed to write translation file", ['tempFile' => $tempFile]);
            throw new \Exception('Failed to write translation file');
        }

        if (!rename($tempFile, $path)) {
            @unlink($tempFile);
            $this->logError("Failed to move translation file", [
                'from' => $tempFile,
                'to' => $path,
            ]);
            throw new \Exception('Failed to move translation file');
        }

        $this->logInfo("Successfully wrote translation file", ['path' => $path]);
    }
}
