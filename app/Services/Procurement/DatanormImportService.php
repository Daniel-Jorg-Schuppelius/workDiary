<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatanormImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SupplierCatalogSource;
use CommonToolkit\Helper\Data\StringHelper;
use RuntimeException;

/**
 * Importiert eine DATANORM-Katalogdatei (4/5) in die Katalogartikel einer Quelle
 * (Feature 050, „Später": strukturierte Katalogformate). Verarbeitet die
 * Artikel-Hauptsätze (Satzart `A`) und übergibt sie als normalisierte Datensätze
 * dem {@see CatalogItemUpserter}. Behandelt die Datei als vollständigen
 * Katalog-Snapshot (nicht enthaltene Artikel werden abgekündigt).
 *
 * `A`-Satz (semikolon-getrennt):
 *   A;VK;Artikelnr;Textkz;Kurztext1;Kurztext2;Preiskz;Preiseinheit;
 *   Mengeneinheit;Preis(Cent);Rabattgruppe;Warengruppe;…
 */
class DatanormImportService {
    public function __construct(private readonly CatalogItemUpserter $upserter = new CatalogItemUpserter()) {}

    /**
     * @return array{rows: int, created: int, updated: int, unchanged: int, price_changed: int, discontinued: int}
     *
     * @throws RuntimeException Wenn die Datei keine Artikelsätze enthält.
     */
    public function import(SupplierCatalogSource $source, string $content): array {
        $records = $this->parse($source, $content);
        if ($records === []) {
            throw new RuntimeException((string) __('procurement.catalog.error.no_articles'));
        }

        return $this->upserter->persist($source, $records, $content);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parse(SupplierCatalogSource $source, string $content): array {
        // Feature 052: Encoding-Konvertierung über das Common-Toolkit.
        $content = StringHelper::convertToUtf8($content, $source->encoding);

        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        $records = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, 'A;')) {
                continue; // nur Artikel-Hauptsätze; Vorlauf/Texte/Preissätze ignorieren
            }

            $f = explode(';', $line);
            $action = strtoupper(trim($f[1] ?? ''));
            $externalNo = trim($f[2] ?? '');
            if ($externalNo === '' || $action === 'L' || $action === 'D') {
                continue; // Löschsätze: das Fehlen im Snapshot kündigt ohnehin ab
            }

            $name = trim(trim($f[4] ?? '') . ' ' . trim($f[5] ?? ''));

            $records[] = [
                'external_no' => $externalNo,
                'name' => $name !== '' ? $name : $externalNo,
                'category' => trim($f[11] ?? '') ?: null,
                'purchase_price' => $this->unitPrice($f[9] ?? '', $f[7] ?? '1'),
                'currency' => 'EUR',
                'pack_size' => '1',
                'base_qty' => '1',
            ];
        }

        return $records;
    }

    /**
     * DATANORM-Preis (kleinste Währungseinheit) je Preiseinheit → Stückpreis.
     *
     * @return numeric-string|null
     */
    private function unitPrice(string $price, string $priceUnit): ?string {
        $cents = (int) preg_replace('/\D/', '', $price);
        if ($cents <= 0) {
            return null;
        }
        $unit = max(1, (int) preg_replace('/\D/', '', $priceUnit));

        // Bewusst float/number_format (Klasse F): eine bcmath-Variante rundet
        // exakte .00005-Ties (Preiseinheit 1000) anders als das etablierte
        // float-Verhalten — Umstellung nur nach fachlicher Abnahme der
        // Tie-Semantik (siehe ../WorkDiary-Architecture/toolkit-audit-2026-06.md).
        return number_format(($cents / 100) / $unit, 4, '.', '');
    }
}
