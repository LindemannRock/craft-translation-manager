<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * Translation source registry and permission helpers
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

namespace lindemannrock\translationmanager\services;

use craft\base\Component;
use lindemannrock\base\helpers\PluginHelper;
use lindemannrock\translationmanager\models\TranslationSource;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Registry for configured and provider-backed translation sources.
 *
 * @since 5.30.0
 */
class SourceService extends Component
{
    public const ACTION_EDIT = 'edit';
    public const ACTION_APPROVE = 'approve';
    public const ACTION_DELETE_UNUSED = 'deleteUnused';
    public const ACTION_CAPTURE = 'capture';
    public const ACTION_GENERATE = 'generate';
    public const ACTION_DELETE = 'delete';

    /**
     * @return TranslationSource[]
     */
    public function getAllSources(): array
    {
        $sources = [];
        $settings = TranslationManager::getInstance()->getSettings();

        foreach ($settings->getEnabledCategories() as $category) {
            $sources[] = new TranslationSource(
                id: $category,
                label: $this->labelFromCategory($category),
                type: TranslationSource::TYPE_CATEGORY,
                category: $category,
            );
        }

        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        foreach ($integrationService->getIntegrationsBySourceType('forms') as $integration) {
            if (!$integration->isAvailable()) {
                continue;
            }

            $providerName = $integration->getName();
            $sources[] = new TranslationSource(
                id: $integration->getCategory(),
                label: PluginHelper::getPluginName($integration->getPluginHandle(), ucfirst($providerName)),
                type: TranslationSource::TYPE_PROVIDER,
                category: $integration->getCategory(),
                providerName: $providerName,
                contextPrefix: $integration->getContextPrefix(),
                pluginHandle: $integration->getPluginHandle(),
            );
        }

        $unique = [];
        foreach ($sources as $source) {
            $unique[$source->id] = $source;
        }

        return array_values($unique);
    }

    public function getSourceById(string $sourceId): ?TranslationSource
    {
        foreach ($this->getAllSources() as $source) {
            if ($source->id === $sourceId) {
                return $source;
            }
        }

        return null;
    }

    public function getSourceForRecord(TranslationRecord $record): ?TranslationSource
    {
        return $this->getSourceForContextAndCategory((string)$record->context, (string)$record->category);
    }

    public function getSourceForContextAndCategory(string $context, string $category): ?TranslationSource
    {
        /** @var IntegrationService $integrationService */
        $integrationService = TranslationManager::getInstance()->get('integrations');
        $integration = $integrationService->getIntegrationForContext($context)
            ?? $integrationService->getIntegrationForCategory($category);

        if ($integration !== null) {
            return $this->getSourceById($integration->getCategory());
        }

        return $this->getSourceById($category);
    }

    public function getAllPermission(string $action): string
    {
        return match ($action) {
            self::ACTION_EDIT => 'translationManager:editAllTranslations',
            self::ACTION_APPROVE => 'translationManager:approveAllTranslations',
            self::ACTION_DELETE_UNUSED => 'translationManager:deleteUnusedAllTranslations',
            self::ACTION_CAPTURE => 'translationManager:captureAllTranslations',
            self::ACTION_GENERATE => 'translationManager:generateAllSources',
            self::ACTION_DELETE => 'translationManager:deleteAllSourceTranslations',
            default => throw new \InvalidArgumentException("Unknown source action: {$action}"),
        };
    }

    public function getSourcePermission(string $action, string $sourceId): string
    {
        $prefix = match ($action) {
            self::ACTION_EDIT => 'translationManager:editSource',
            self::ACTION_APPROVE => 'translationManager:approveSource',
            self::ACTION_DELETE_UNUSED => 'translationManager:deleteUnusedSource',
            self::ACTION_CAPTURE => 'translationManager:captureTranslations',
            self::ACTION_GENERATE => 'translationManager:generateSource',
            self::ACTION_DELETE => 'translationManager:deleteSourceTranslations',
            default => throw new \InvalidArgumentException("Unknown source action: {$action}"),
        };

        return $prefix . ':' . $sourceId;
    }

    public function currentUserCan(string $action, string $sourceId): bool
    {
        $user = \Craft::$app->getUser();

        return $this->hasPermission(
            $action,
            $sourceId,
            static fn(string $permission): bool => $user->checkPermission($permission),
        );
    }

    /**
     * @param callable(string): bool $permissionChecker
     */
    public function hasPermission(string $action, string $sourceId, callable $permissionChecker): bool
    {
        return $permissionChecker($this->getAllPermission($action))
            || $permissionChecker($this->getSourcePermission($action, $sourceId));
    }

    public function currentUserCanRecord(string $action, TranslationRecord $record): bool
    {
        $source = $this->getSourceForRecord($record);

        return $source !== null && $this->currentUserCan($action, $source->id);
    }

    public function currentUserCanAny(string $action): bool
    {
        $user = \Craft::$app->getUser();
        if ($user->checkPermission($this->getAllPermission($action))) {
            return true;
        }

        foreach ($this->getAllSources() as $source) {
            if ($user->checkPermission($this->getSourcePermission($action, $source->id))) {
                return true;
            }
        }

        return false;
    }

    private function labelFromCategory(string $category): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $category));
    }
}
