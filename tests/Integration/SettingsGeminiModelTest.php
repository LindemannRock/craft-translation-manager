<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\models\Settings;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * Pins the `geminiModel` format rule. The Gemini provider interpolates the
 * model name into the request URL path, so the setting must reject characters
 * that could alter the path (slashes, query separators, whitespace). Guards
 * the defense-in-depth half of audit 4.5 (the other half is rawurlencode in
 * GeminiProvider).
 *
 * @since 5.25.0
 */
final class SettingsGeminiModelTest extends TestCase
{
    public function testInvalidGeminiModelIsRejected(): void
    {
        $invalidModels = [
            'path traversal' => 'gemini/../../models/evil',
            'query injection' => 'gemini-2.0-flash?key=leak',
            'slash' => 'models/gemini-2.0-flash',
            'whitespace' => 'gemini 2.0 flash',
        ];

        foreach ($invalidModels as $label => $model) {
            $settings = new Settings();
            $settings->geminiModel = $model;
            $settings->validate(['geminiModel']);

            self::assertTrue(
                $settings->hasErrors('geminiModel'),
                "Expected the {$label} value '{$model}' to be rejected by the geminiModel format rule.",
            );
        }
    }

    public function testValidGeminiModelPasses(): void
    {
        $settings = new Settings();
        $settings->geminiModel = 'gemini-2.0-flash';
        $settings->validate(['geminiModel']);

        self::assertFalse($settings->hasErrors('geminiModel'));
    }
}
