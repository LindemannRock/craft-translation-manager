<?php
/**
 * LindemannRock Translation Manager
 *
 * @link      https://lindemannrock.com
 * @copyright Copyright (c) 2026 LindemannRock
 */

declare(strict_types=1);

namespace lindemannrock\translationmanager\tests\Integration;

use craft\helpers\Db;
use lindemannrock\translationmanager\integrations\FormieIntegration;
use lindemannrock\translationmanager\records\TranslationRecord;
use lindemannrock\translationmanager\tests\TestCase;

/**
 * Pins the bulk-mark-unused contract for `BaseIntegration::markTranslationsUnused()`:
 * it flips every passed row to `unused` in ONE bulk UPDATE (not a findOne()+save()
 * per id), skips rows already `unused`, and returns the count actually flipped.
 *
 * Regression guard for the N+1 on the form-delete path (audit 4.2): a deleted form
 * can produce hundreds of rows, and the old per-row loop issued a SELECT+UPDATE for
 * each one.
 *
 * @since 5.25.0
 */
final class IntegrationMarkTranslationsUnusedTest extends TestCase
{
    public function testMarksPassedRowsUnusedAndSkipsAlreadyUnused(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $source = self::MARKER . 'markunused_' . bin2hex(random_bytes(4));
        $this->translations->createOrUpdateTranslation($source, 'site');

        $ids = array_map('intval', array_column($this->fetchRowsForSource($source), 'id'));
        self::assertGreaterThan(0, count($ids), 'Seeding must create at least one row.');

        // Pre-set the first row to 'unused' so the skip branch is exercised.
        Db::update(
            TranslationRecord::tableName(),
            ['status' => 'unused'],
            ['id' => $ids[0]],
        );
        $expectedFlipped = count($ids) - 1;

        $marked = $this->invokeMarkUnused($ids);

        self::assertSame(
            $expectedFlipped,
            $marked,
            'Return count must reflect only rows actually flipped (already-unused excluded).',
        );
        foreach ($this->fetchRowsForSource($source) as $row) {
            self::assertSame('unused', $row['status'], 'Every passed row should end up unused.');
        }
    }

    public function testSecondCallIsIdempotentAndReturnsZero(): void
    {
        $this->requireLatinSourceLanguage();
        $this->requireAtLeastOneSite();

        $source = self::MARKER . 'markunused2_' . bin2hex(random_bytes(4));
        $this->translations->createOrUpdateTranslation($source, 'site');
        $ids = array_map('intval', array_column($this->fetchRowsForSource($source), 'id'));

        $first = $this->invokeMarkUnused($ids);
        self::assertSame(count($ids), $first, 'First call flips all non-unused rows.');

        $second = $this->invokeMarkUnused($ids);
        self::assertSame(0, $second, 'Second call must flip nothing — guards against double-marking.');
    }

    public function testEmptyIdListReturnsZero(): void
    {
        self::assertSame(0, $this->invokeMarkUnused([]));
    }

    /**
     * Invoke the protected `BaseIntegration::markTranslationsUnused()` on a real
     * FormieIntegration instance. The method touches only the DB + getName(), so
     * it runs without Formie installed.
     *
     * @param array<int, int> $ids
     */
    private function invokeMarkUnused(array $ids): int
    {
        $integration = new FormieIntegration();
        $method = new \ReflectionMethod($integration, 'markTranslationsUnused');
        $method->setAccessible(true);

        return (int) $method->invoke($integration, $ids);
    }
}
