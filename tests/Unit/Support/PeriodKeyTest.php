<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodKeyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Casts\PeriodKeyCast;
use App\Enums\Period\PeriodKind;
use App\Models\CarryForward\CarryForwardNote;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Periodenschlüssel (Feature 198/201): Schreibweise `2026`, `2026-H1`,
 * `2026-Q3`, `2026-03`; MVP-1634 setzt `diary_entries.period_key` in genau
 * dieser Form. Vergleiche über Tagesgrenzen, nie über Text.
 */
final class PeriodKeyTest extends TestCase {
    /** @return array<string, array{string, PeriodKind, int, int, string, string}> */
    public static function keys(): array {
        return [
            'Jahr' => ['2026', PeriodKind::Year, 2026, 1, '2026-01-01', '2026-12-31'],
            'erstes Halbjahr' => ['2026-H1', PeriodKind::HalfYear, 2026, 1, '2026-01-01', '2026-06-30'],
            'zweites Halbjahr' => ['2026-H2', PeriodKind::HalfYear, 2026, 2, '2026-07-01', '2026-12-31'],
            'drittes Quartal' => ['2026-Q3', PeriodKind::Quarter, 2026, 3, '2026-07-01', '2026-09-30'],
            'Februar Schaltjahr' => ['2028-02', PeriodKind::Month, 2028, 2, '2028-02-01', '2028-02-29'],
            'Dezember' => ['2025-12', PeriodKind::Month, 2025, 12, '2025-12-01', '2025-12-31'],
        ];
    }

    #[DataProvider('keys')]
    public function test_parses_formats_and_bounds_every_kind(string $key, PeriodKind $kind, int $year, int $ordinal, string $start, string $end): void {
        $period = PeriodKey::parse($key);

        $this->assertSame($kind, $period->kind);
        $this->assertSame($year, $period->year);
        $this->assertSame($ordinal, $period->ordinal);
        $this->assertSame($key, $period->key());
        $this->assertSame($key, (string) $period);
        $this->assertSame($start, $period->start()->format('Y-m-d'));
        $this->assertSame($end, $period->end()->format('Y-m-d'));
        $this->assertTrue($period->equals(PeriodKey::of($kind, $year, $ordinal)));
    }

    public function test_parse_normalizes_case_and_whitespace(): void {
        $this->assertSame('2026-H2', PeriodKey::parse(' 2026-h2 ')->key());
        $this->assertSame('2026-Q1', PeriodKey::parse('2026-q1')->key());
    }

    /** @return array<string, array{string}> */
    public static function invalidKeys(): array {
        return [
            'leer' => [''],
            'Monat 13' => ['2026-13'],
            'Monat 0' => ['2026-00'],
            'Halbjahr 3' => ['2026-H3'],
            'Quartal 5' => ['2026-Q5'],
            'Buchhaltung Monat' => ['2026-M03'],
            'Buchhaltung Jahr' => ['2026-J'],
            'einstelliger Monat' => ['2026-3'],
            'zweistelliges Jahr' => ['26'],
            'Datum' => ['2026-03-01'],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_rejects_invalid_keys(string $key): void {
        $this->assertNull(PeriodKey::tryParse($key));

        $this->expectException(InvalidArgumentException::class);
        PeriodKey::parse($key);
    }

    public function test_of_rejects_ordinals_outside_the_kind(): void {
        $this->expectException(InvalidArgumentException::class);
        PeriodKey::of(PeriodKind::Quarter, 2026, 5);
    }

    public function test_counts_forward_and_backward_across_year_boundaries(): void {
        $this->assertSame('2027', PeriodKey::parse('2026')->next()->key());
        $this->assertSame('2027-H1', PeriodKey::parse('2026-H2')->next()->key());
        $this->assertSame('2027-Q1', PeriodKey::parse('2026-Q4')->next()->key());
        $this->assertSame('2027-01', PeriodKey::parse('2026-12')->next()->key());
        $this->assertSame('2025-12', PeriodKey::parse('2026-01')->previous()->key());
        $this->assertSame('2025-Q4', PeriodKey::parse('2026-Q1')->previous()->key());
        $this->assertSame('2028-03', PeriodKey::parse('2026-03')->next(24)->key());
        $this->assertSame('2024-H2', PeriodKey::parse('2026-H1')->previous(3)->key());
    }

    public function test_containing_maps_a_date_into_each_kind(): void {
        $date = CarbonImmutable::parse('2026-08-15');

        $this->assertSame('2026', PeriodKey::containing(PeriodKind::Year, $date)->key());
        $this->assertSame('2026-H2', PeriodKey::containing(PeriodKind::HalfYear, $date)->key());
        $this->assertSame('2026-Q3', PeriodKey::containing(PeriodKind::Quarter, $date)->key());
        $this->assertSame('2026-08', PeriodKey::containing(PeriodKind::Month, $date)->key());
        $this->assertTrue(PeriodKey::parse('2026-Q3')->contains($date));
        $this->assertFalse(PeriodKey::parse('2026-Q4')->contains($date));
    }

    public function test_compares_chronologically_not_textually(): void {
        // Textvergleich hielte H1 für früher als Q1 und 2026-10 für früher als 2026-9 (gäbe es das).
        $this->assertSame(0, PeriodKey::parse('2026-H1')->compareTo(PeriodKey::parse('2026-H1')));
        $this->assertSame(-1, PeriodKey::parse('2026-Q4')->compareTo(PeriodKey::parse('2027-Q1')));
        $this->assertSame(1, PeriodKey::parse('2026-10')->compareTo(PeriodKey::parse('2026-09')));
        // Über Arten: gleicher Beginn → die kürzere zuerst; sonst nach Beginn.
        $this->assertSame(-1, PeriodKey::parse('2026-Q1')->compareTo(PeriodKey::parse('2026-H1')));
        $this->assertSame(1, PeriodKey::parse('2026-H2')->compareTo(PeriodKey::parse('2026-Q2')));
        $this->assertSame(1, PeriodKey::parse('2026-03')->compareTo(PeriodKey::parse('2026')));
    }

    public function test_ends_before_means_wholly_earlier(): void {
        $this->assertTrue(PeriodKey::parse('2025')->endsBefore(PeriodKey::parse('2026')));
        $this->assertTrue(PeriodKey::parse('2025-12')->endsBefore(PeriodKey::parse('2026')));
        $this->assertTrue(PeriodKey::parse('2026-Q1')->endsBefore(PeriodKey::parse('2026-Q2')));
        // Überschneidung ist nicht „davor“: das Jahr 2026 enthält das zweite Quartal.
        $this->assertFalse(PeriodKey::parse('2026')->endsBefore(PeriodKey::parse('2026-Q2')));
        $this->assertFalse(PeriodKey::parse('2026-03')->endsBefore(PeriodKey::parse('2026')));
        $this->assertFalse(PeriodKey::parse('2026')->endsBefore(PeriodKey::parse('2026')));
        $this->assertFalse(PeriodKey::parse('2027')->endsBefore(PeriodKey::parse('2026-H2')));
    }

    public function test_labels_follow_the_locale(): void {
        app()->setLocale('de');
        $this->assertSame('2026', PeriodKey::parse('2026')->label());
        $this->assertSame('1. Halbjahr 2026', PeriodKey::parse('2026-H1')->label());
        $this->assertSame('3. Quartal 2026', PeriodKey::parse('2026-Q3')->label());
        $this->assertSame('März 2026', PeriodKey::parse('2026-03')->label());

        app()->setLocale('en');
        $this->assertSame('Q3 2026', PeriodKey::parse('2026-Q3')->label());
        $this->assertSame('March 2026', PeriodKey::parse('2026-03')->label());
        $this->assertSame('Quarter', PeriodKind::Quarter->label());
    }

    public function test_cast_stores_the_canonical_key_and_reads_the_value_object(): void {
        $cast = new PeriodKeyCast;
        $model = new CarryForwardNote;

        $this->assertSame('2026-Q2', $cast->set($model, 'period_key', ' 2026-q2', []));
        $this->assertSame('2026-H1', $cast->set($model, 'period_key', PeriodKey::parse('2026-H1'), []));
        $this->assertNull($cast->set($model, 'period_key', '', []));
        $this->assertTrue(PeriodKey::parse('2026-07')->equals($cast->get($model, 'period_key', '2026-07', []) ?? PeriodKey::parse('1999')));
        $this->assertNull($cast->get($model, 'period_key', 'Altbestand', []));

        $this->expectException(InvalidArgumentException::class);
        $cast->set($model, 'period_key', '2026-13', []);
    }
}
