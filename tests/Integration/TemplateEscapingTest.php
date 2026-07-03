<?php
/**
 * Translation Manager plugin for Craft CMS 5.x
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\tests\TestCase;

/**
 * @since 5.34.0
 */
final class TemplateEscapingTest extends TestCase
{
    /**
     * @dataProvider pathInfoBoxTemplates
     */
    public function testPathInfoBoxesEscapeDynamicPaths(string $templatePath, string $variable): void
    {
        $contents = (string)file_get_contents($templatePath);

        self::assertStringContainsString($variable . '|e', $contents);
        self::assertStringNotContainsString('~ ' . $variable . ' ~', $contents);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pathInfoBoxTemplates(): array
    {
        $templateRoot = dirname(__DIR__, 2) . '/src/templates';

        return [
            'backups index backup path' => [$templateRoot . '/backups/index.twig', 'backupPath'],
            'generate index generation path' => [$templateRoot . '/generate/index.twig', 'generationPath'],
            'settings backup path' => [$templateRoot . '/settings/backup.twig', 'backupPath'],
            'settings generation path' => [$templateRoot . '/settings/generation.twig', 'generationPath'],
        ];
    }
}
