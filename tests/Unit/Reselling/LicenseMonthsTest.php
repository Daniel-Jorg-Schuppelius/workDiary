<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseMonthsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Plugins\Lexoffice\Services\LexofficeInvoiceMirrorSource;
use App\Services\Reselling\Mirror\MirrorLine;
use App\Services\Reselling\Register\LicenseMonths;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Lizenzen × Monate einer Rechnungsposition (Feature 152): Trennung nach
 * Einheit und Leistungszeitraum, Anzeige, Mengenumrechnung, Monatsartikel,
 * Monate zwischen Inklusiv-Daten. Ohne Datenbank — Modelle im Speicher,
 * über die Lexoffice-Quelle in die Spiegelzeile übersetzt.
 */
class LicenseMonthsTest extends TestCase {
    private function line(float $quantity, ?string $unit, string $net = '20.60', ?string $serviceFrom = null, ?string $serviceTo = null): MirrorLine {
        $line = new LexofficeVoucherLine(['quantity' => $quantity, 'unit_name' => $unit, 'unit_net' => $net, 'total_net' => $net, 'currency' => 'EUR', 'name' => 'Microsoft 365 Business Premium']);
        $line->setRelation('voucher', new LexofficeVoucher(['voucher_date' => '2026-01-05', 'service_starts_on' => $serviceFrom, 'service_ends_on' => $serviceTo, 'currency' => 'EUR']));

        return (new LexofficeInvoiceMirrorSource)->toLine($line, canPreview: false);
    }

    public function test_split_separates_licences_from_months(): void {
        // „12 Monat" ohne Zeitraum: eine Lizenz über zwölf Monate.
        $this->assertSame(['licences' => 1.0, 'months' => 12.0], LicenseMonths::split($this->line(12, 'Monat')));
        // „24 Monat" bei zwölf Monaten Leistung: Menge ÷ Leistungsmonate = zwei Lizenzen.
        $this->assertSame(['licences' => 2.0, 'months' => 12.0], LicenseMonths::split($this->line(24, 'Monat', '3.95', '2025-01-01', '2025-12-31')));
        // Menge kleiner als die Leistungsmonate: keine Teilung, Menge = Monate.
        $this->assertSame(['licences' => 1.0, 'months' => 6.0], LicenseMonths::split($this->line(6, 'Monat', '3.95', '2025-01-01', '2025-12-31')));
        // „2 Jahr" mit 24 Monaten Leistung ist EINE Lizenz über zwei Jahre …
        $this->assertSame(['licences' => 1.0, 'months' => 24.0], LicenseMonths::split($this->line(2, 'Jahr', '247.20', '2024-02-07', '2026-02-06')));
        // … ohne Zeitraum zwei Lizenzen × 12.
        $this->assertSame(['licences' => 2.0, 'months' => 12.0], LicenseMonths::split($this->line(2, 'Jahr', '247.20')));
        // Stück ohne Zeitraum: Menge = Lizenzen, ein Jahr; mit Zeitraum dessen Länge.
        $this->assertSame(['licences' => 5.0, 'months' => 12.0], LicenseMonths::split($this->line(5, 'Stück', '47.40')));
        $this->assertSame(['licences' => 5.0, 'months' => 24.0], LicenseMonths::split($this->line(5, 'Stück', '94.80', '2024-01-01', '2025-12-31')));
        // Ohne Einheit entscheidet der Stückpreis: unter 30 € = Monatsposition.
        $this->assertSame(['licences' => 1.0, 'months' => 12.0], LicenseMonths::split($this->line(12, null, '3.95')));
        $this->assertSame(['licences' => 3.0, 'months' => 12.0], LicenseMonths::split($this->line(3, null, '145.56')));
        $this->assertSame(60.0, LicenseMonths::ofLine($this->line(5, 'Jahr')));
    }

    public function test_licence_count_is_certain_only_with_unit_or_service_period(): void {
        $this->assertFalse(LicenseMonths::isLicenceCountCertain($this->line(24, 'Monat')), '„24 Monat" allein: 2 × 12 oder 1 × 24');
        $this->assertTrue(LicenseMonths::isLicenceCountCertain($this->line(24, 'Monat', '3.95', '2025-01-01', '2025-12-31')));
        $this->assertTrue(LicenseMonths::isLicenceCountCertain($this->line(2, 'Jahr')));
        $this->assertTrue(LicenseMonths::isLicenceCountCertain($this->line(2, 'Stück')));
    }

    public function test_label_shows_licences_times_months(): void {
        $this->assertSame('5 × 12 ' . __('resale.link.months_short'), LicenseMonths::label(60.0, 12.0));
        $this->assertSame('6 ' . __('resale.link.months_short'), LicenseMonths::label(6.0, 12.0), 'Rest unter einer Lizenz: nur Monate');
        $this->assertSame((string) __('resale.link.licence_months_only', ['months' => '18']), LicenseMonths::label(18.0, 12.0), 'keine ganze Lizenzzahl');
        $this->assertSame((string) __('resale.link.licence_months_only', ['months' => '12']), LicenseMonths::label(12.0, 0.0));
    }

    public function test_units_for_converts_licence_months_back_to_the_line_unit(): void {
        $this->assertSame(1.0, LicenseMonths::unitsFor($this->line(5, 'Jahr'), 12.0, 12), 'Jahr: 12 Monate = 1 Stück');
        $this->assertSame(12.0, LicenseMonths::unitsFor($this->line(12, 'Monat'), 12.0, 12), 'Monat: Stückpreis je Lizenzmonat');
        $this->assertSame(0.5, LicenseMonths::unitsFor($this->line(2, 'Stück', '94.80', '2024-01-01', '2025-12-31'), 12.0, 12), 'Stück über 24 Monate: 12 Monate = halbes Stück');
        $this->assertSame(1.0, LicenseMonths::unitsFor($this->line(2, 'Stück', '47.40'), 12.0, 12));
    }

    public function test_month_unit_recognises_german_and_english_singular_and_plural(): void {
        foreach (['Monat', 'monate', 'MONTH', 'Months', ' Monat '] as $unit) {
            $this->assertTrue(LicenseMonths::isMonthUnit($unit), $unit);
        }
        foreach (['Jahr', 'Stück', '', null] as $unit) {
            $this->assertFalse(LicenseMonths::isMonthUnit($unit), (string) $unit);
        }
    }

    public function test_months_between_inclusive_dates(): void {
        $this->assertSame(12, LicenseMonths::monthsBetween(CarbonImmutable::parse('2025-12-01'), CarbonImmutable::parse('2026-11-30')), 'Jahreswechsel');
        $this->assertSame(1, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-01-31'), CarbonImmutable::parse('2026-02-28')), 'kurzer Februar');
        $this->assertSame(24, LicenseMonths::monthsBetween(CarbonImmutable::parse('2024-02-07'), CarbonImmutable::parse('2026-02-06')));
        $this->assertSame(0, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-05')), 'Einzeltage runden auf 0');
        $this->assertSame(1, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-31')));
        $this->assertSame(12, LicenseMonths::monthsBetween(CarbonImmutable::parse('2025-12-15'), CarbonImmutable::parse('2026-12-14')), 'Co-Term über den Jahreswechsel');
        $this->assertSame(0, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-15')), '15/31 rundet ab');
        $this->assertSame(1, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-20')), '20/31 rundet auf');
        $this->assertSame(0, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-01-01')), 'Ende vor Beginn (fremde Belegdaten) ist 0, keine Exception');
        $this->assertSame(1, LicenseMonths::monthsBetween(CarbonImmutable::parse('2026-03-28 23:30', 'Europe/Berlin'), CarbonImmutable::parse('2026-04-27 00:15', 'Europe/Berlin')), 'nur Kalendertage zählen, nicht Uhrzeit oder Sommerzeit');
    }
}
