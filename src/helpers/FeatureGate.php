<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\helpers;

use lindemannrock\base\helpers\ExperimentalFeatureHelper;

/**
 * Internal feature gates for unreleased Translation Manager functionality.
 *
 * @since 5.30.0
 */
final class FeatureGate
{
    public const AI_TRANSLATION_ENV_FLAG = 'TRANSLATION_MANAGER_ENABLE_AI';

    public static function aiTranslationsEnabled(): bool
    {
        return ExperimentalFeatureHelper::isEnabled(self::AI_TRANSLATION_ENV_FLAG, true);
    }

    public static function requireAiTranslationsEnabled(): void
    {
        ExperimentalFeatureHelper::requireEnabled(self::AI_TRANSLATION_ENV_FLAG, true);
    }
}
