<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericSubscriptionReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Reselling;

use App\Enums\Reselling\{BillingFrequency, SubscriptionProvider};
use App\Services\Reselling\Marketplace\GenericSubscriptionReader;
use DateTimeImmutable;
use RuntimeException;
use Tests\TestCase;

/**
 * Generische Abo-Liste (Review 2026-09-10, B15/B16/B19/C3/C4/C5): XLSX mit
 * echten Datums- und Zahlzellen, englische Überschriften, deutsche Zahlen,
 * Datumsvarianten, Befunde je Zeile statt stiller Annahmen.
 */
class GenericSubscriptionReaderTest extends TestCase {
    use BuildsXlsxFixtures;

    /**
     * @param  list<string>  $rows
     */
    private function csv(array $rows, string $header = 'Kennung;Firma;Produkt;Menge;Beginn;Ende;Intervall;Laufzeit (Monate);Einkaufspreis;Verkaufspreis;Gesamt;Währung'): string {
        $path = sys_get_temp_dir() . '/generic-' . uniqid() . '.csv';
        file_put_contents($path, "\xEF\xBB\xBF" . $header . "\n" . implode("\n", $rows) . "\n");

        return $path;
    }

    public function test_xlsx_with_date_and_number_cells_and_english_headers(): void {
        $path = self::xlsxFixture([['Abos', [
            ['Contract ID', 'Customer', 'Product', 'Qty', 'Start date', 'End date', 'Interval', 'Purchase price (EUR)', 'Sale price', 'Currency'],
            ['C-1', 'Müller GmbH', 'M365 Business Premium', 3, new DateTimeImmutable('2026-02-01'), null, 'yearly', 187.92, 247.2, 'EUR'],
            ['C-2', 'Beispiel AG', 'Exchange Online Plan 1', 2, 46054, new DateTimeImmutable('2027-01-31'), 'monthly', 1.234, null, null],
            ['C-3', 'Text AG', 'Exchange Online Plan 1', 1, '1/2/2026', null, 'monthly', '1.200', '3,0325', 'CHF'],
        ]]]);
        try {
            $import = (new GenericSubscriptionReader)->read($path, SubscriptionProvider::Manual);
        } finally {
            @unlink($path);
        }

        $this->assertSame([], $import->issues);
        $this->assertCount(3, $import->entitlements);
        [$a, $b, $c] = $import->entitlements;
        $this->assertSame('2026-02-01', $a->startsOn->toDateString(), 'Excel-Datumszelle');
        $this->assertSame(3, $a->quantity);
        $this->assertSame('187.9200', $a->unitFee?->getAmount(), 'Zahlzelle exakt, Scale 4');
        $this->assertSame('563.76', $a->fee->getAmount(), 'Gesamt = Stück × Menge mit Scale 2');
        $this->assertSame('247.2000', $a->salePrice?->getAmount());
        $this->assertSame(BillingFrequency::Yearly, $a->frequency);
        $this->assertSame(2, $a->sourceLine);

        $this->assertSame('2026-02-01', $b->startsOn->toDateString(), 'Excel-Seriennummer 46054 als Zahl');
        $this->assertSame('2027-01-31', $b->endsOn?->toDateString());
        $this->assertSame('1.2340', $b->unitFee?->getAmount(), 'Float 1.234 ist kein Tausenderpunkt');
        $this->assertSame('EUR', $b->unitFee?->getCurrency()->value, 'ohne Währungsspalte Euro');

        $this->assertSame('2026-02-01', $c->startsOn->toDateString(), '„1/2/2026" deutsch: 1. Februar');
        $this->assertSame('1200.0000', $c->unitFee?->getAmount(), 'Text „1.200" ist deutsch 1200');
        $this->assertSame('3.0325', $c->salePrice?->getAmount(), 'vier Nachkommastellen bleiben');
        $this->assertSame('CHF', $c->unitFee?->getCurrency()->value);
    }

    public function test_xlsx_missing_required_column_is_reported_translated(): void {
        $path = self::xlsxFixture([['Abos', [['Firma', 'Produkt'], ['A', 'B']]]]);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage(__('resale_import.file.missing_columns', ['columns' => __('resale_import.column.start')]));
            (new GenericSubscriptionReader)->read($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_unreadable_dates_are_issues_not_guesses(): void {
        $path = $this->csv([
            'A;A GmbH;Prod;1;2026;;;;;;;',
            'B;B GmbH;Prod;1;3.2026;;;;;;;',
            'C;C GmbH;Prod;1;31.02.2026;;;;;;;',
            'D;D GmbH;Prod;1;01.02.2026;1.2.2026;;;;;;',
            'E;E GmbH;Prod;1;46054;;;;;;;',
            'F;F GmbH;Prod;1;2026-02-01 00:00:00;;;;;;;',
        ]);
        try {
            $import = (new GenericSubscriptionReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(2, $import->entitlements);
        $this->assertSame('2026-02-01', $import->entitlements[0]->startsOn->toDateString(), 'Seriennummer als Text');
        $this->assertSame('2026-02-01', $import->entitlements[1]->startsOn->toDateString(), 'ISO mit Uhrzeit');
        $this->assertCount(4, $import->issues);
        $this->assertStringContainsString('Zeile 2', $import->issues[0]);
        $this->assertStringContainsString('"2026"', $import->issues[0]);
        $this->assertStringContainsString('"3.2026"', $import->issues[1]);
        $this->assertStringContainsString('"31.02.2026"', $import->issues[2]);
        $this->assertStringContainsString('Zeile 5', $import->issues[3]);
        $this->assertStringContainsString(__('resale_import.row.end_before_start', ['line' => 5, 'company' => 'D GmbH']), $import->issues[3], 'Ende = Beginn ist kein Zeitraum');
    }

    public function test_quantity_with_unit_zero_or_fraction_skips_the_row(): void {
        $path = $this->csv([
            'A;A GmbH;Prod;4 Stück;01.02.2026;;;;;;;',
            'B;B GmbH;Prod;0;01.02.2026;;;;;;;',
            'C;C GmbH;Prod;8 S;01.02.2026;;;;;;;',
            'D;D GmbH;Prod;2,5;01.02.2026;;;;;;;',
            'E;E GmbH;Prod;;01.02.2026;;;;;;;',
            'F;F GmbH;Prod;3;01.02.2026;;;;;;100;',
        ]);
        try {
            $import = (new GenericSubscriptionReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(4, $import->issues);
        foreach (['4 Stück', '0', '8 S', '2,5'] as $i => $value) {
            $this->assertStringContainsString('"' . $value . '"', $import->issues[$i]);
            $this->assertStringContainsString('Zeile ' . ($i + 2), $import->issues[$i]);
        }
        $this->assertCount(2, $import->entitlements);
        $this->assertSame(1, $import->entitlements[0]->quantity, 'leere Menge = 1');
        $this->assertSame(3, $import->entitlements[1]->quantity);
        $this->assertSame('33.3333', $import->entitlements[1]->unitFee?->getAmount(), 'Gesamt 100 / 3 mit Scale 4 vor dem Teilen');
        $this->assertSame('100.00', $import->entitlements[1]->fee->getAmount());
    }

    public function test_duplicate_ids_explicit_and_derived_are_reported(): void {
        $path = $this->csv([
            'HST-1;A GmbH;Prod;1;01.02.2026;;;;;;;',
            'hst-1;A GmbH;Prod;2;01.03.2026;;;;;;;',
            ';B GmbH;Prod;1;01.02.2026;;;;;;;',
            ';B GmbH;Prod;5;01.02.2026;;;;;;;',
            ';B GmbH;Prod;5;02.02.2026;;;;;;;',
        ]);
        try {
            $import = (new GenericSubscriptionReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(3, $import->entitlements);
        $this->assertCount(2, $import->issues);
        $this->assertSame(__('resale_import.row.duplicate_id', ['line' => 3, 'company' => 'A GmbH', 'value' => 'hst-1', 'other' => 2]), $import->issues[0]);
        $this->assertStringContainsString('Zeile 5', $import->issues[1]);
        $this->assertStringContainsString('Zeile 4', $import->issues[1], 'abgeleitete Kennung aus Firma/Produkt/Beginn kollidiert mit Zeile 4');
        $this->assertSame(1, $import->entitlements[0]->quantity, 'erste Zeile gewinnt');
        $this->assertNotSame($import->entitlements[1]->entitlementId, $import->entitlements[2]->entitlementId, 'anderer Beginn = andere Kennung');
    }

    public function test_german_amounts_and_currency_column(): void {
        $path = $this->csv([
            'A;A GmbH;Prod;1;01.02.2026;;;;1.200;;;',
            "B;B GmbH;Prod;1;01.02.2026;;;;1.234.567;10,70\u{00A0}€;;",
            'C;C GmbH;Prod;1;01.02.2026;;;;3,0325;;;usd',
            'D;D GmbH;Prod;1;01.02.2026;;;;5;;;XYZ',
            'E;E GmbH;Prod;1;01.02.2026;;;12 Monate;;;;',
        ]);
        try {
            $import = (new GenericSubscriptionReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(4, $import->entitlements);
        [$a, $b, $c, $e] = $import->entitlements;
        $this->assertSame('1200.0000', $a->unitFee?->getAmount(), '„1.200" ist 1200, nicht 1,20');
        $this->assertSame('1234567.0000', $b->unitFee?->getAmount());
        $this->assertSame('10.7000', $b->salePrice?->getAmount(), 'geschütztes Leerzeichen und €');
        $this->assertSame('3.0325', $c->unitFee?->getAmount());
        $this->assertSame('USD', $c->unitFee?->getCurrency()->value);
        $this->assertSame('USD', $c->fee->getCurrency()->value);
        $this->assertNull($e->termMonths, 'unlesbare Laufzeit fällt auf den Rhythmus zurück');
        $this->assertCount(2, $import->issues);
        $this->assertStringContainsString('"XYZ"', $import->issues[0]);
        $this->assertStringContainsString('"12 Monate"', $import->issues[1]);
    }

    public function test_ragged_csv_rows_do_not_abort_the_file(): void {
        $path = $this->csv([
            'A;A GmbH;Prod;1;01.02.2026',
            'B;B GmbH;Prod;1;01.02.2026;;;;;;;;extra;zuviel',
            'C;C GmbH;Prod;1;01.02.2026;;;;;;;;;',
        ]);
        try {
            $import = (new GenericSubscriptionReader)->read($path);
        } finally {
            @unlink($path);
        }

        $this->assertCount(2, $import->entitlements, 'zu kurze und nur leer überzählige Zeilen gelten');
        $this->assertCount(1, $import->issues);
        $this->assertSame(__('resale_import.row.too_many_fields', ['line' => 3, 'expected' => 12, 'found' => 14]), $import->issues[0]);
    }
}
