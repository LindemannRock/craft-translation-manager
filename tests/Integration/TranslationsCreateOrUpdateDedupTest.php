<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use lindemannrock\translationmanager\tests\TestCase;
use lindemannrock\translationmanager\TranslationManager;

/**
 * Pins the dedup contract: a second `createOrUpdateTranslation()` call for the
 * same source string MUST NOT create new rows; it must increment `usageCount`
 * on every existing per-language row.
 *
 * This is the core safeguard against the bug class where repeat captures of the
 * same template string spawn duplicate rows (one per scan), bloating the
 * translations table over time and forcing the team into manual cleanup.
 *
 * @since 5.24.0
 */
final class TranslationsCreateOrUpdateDedupTest extends TestCase
{
    public function testSecondCallIncrementsUsageCountWithoutNewRows(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $source = self::MARKER . 'dedup_' . bin2hex(random_bytes(4));
        $expectedRowCount = count(TranslationManager::getInstance()->getUniqueLanguages());

        $this->translations->createOrUpdateTranslation($source, 'site');
        $afterFirst = $this->fetchRowsForSource($source);
        self::assertCount($expectedRowCount, $afterFirst);
        foreach ($afterFirst as $row) {
            self::assertSame(1, (int) $row['usageCount']);
        }

        $this->translations->createOrUpdateTranslation($source, 'site');
        $afterSecond = $this->fetchRowsForSource($source);
        self::assertCount(
            $expectedRowCount,
            $afterSecond,
            'Second call must not create new rows.',
        );
        foreach ($afterSecond as $row) {
            self::assertSame(
                2,
                (int) $row['usageCount'],
                'Every per-language row should have its usageCount incremented.',
            );
        }
    }
}
