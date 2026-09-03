<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconciliationCsvBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

/**
 * Tabellenform des serialisierten Berichts (eine Zeile je Periode) — für
 * Konsole und Oberfläche dieselbe Datei. Geldbeträge ohne Symbol, damit die
 * Spalte in der Tabellenkalkulation rechnet.
 */
final class ReconciliationCsvBuilder {
    /**
     * @return list<string>
     */
    public function header(): array {
        return ['Firma', 'Kunde', 'Lexoffice-Kontakt', 'Zuordnung', 'Abrechnung über', 'Quelle', 'Vertrag/Entitlement', 'Bestellung', 'Anwendung', 'Edition', 'Periode', 'Von', 'Bis', 'Menge', 'Stückpreis Einkauf', 'Gebühr Einkauf', 'Status', 'Rechnungen', 'Netto/Stück min', 'Offen Einkauf', 'Hinweis', 'Ablösung'];
    }

    /**
     * @param  array<string, mixed>  $report  Ausgabe des ReconciliationReportSerializer
     * @return list<list<string>>
     */
    public function rows(array $report): array {
        $rows = [];
        foreach ((array) ($report['findings'] ?? []) as $finding) {
            $rows[] = [
                (string) $finding['company'],
                (string) ($finding['customer'] ?? ''),
                implode(', ', (array) ($finding['contact_ids'] ?? [])),
                (string) ($finding['mapping_source'] ?? ''),
                (string) ($finding['billed_via'] ?? ''),
                (string) $finding['source_label'],
                (string) $finding['entitlement'],
                (string) $finding['order'],
                (string) $finding['application'],
                (string) $finding['edition'],
                (string) $finding['period_index'],
                (string) $finding['from'],
                (string) $finding['to'],
                (string) $finding['quantity'],
                $this->plain($finding['unit_fee']),
                $this->plain($finding['fee']),
                (string) $finding['status_label'],
                implode(', ', (array) $finding['vouchers']),
                $finding['lowest_unit_net'] === null ? '' : $this->plain($finding['lowest_unit_net']),
                $this->plain($finding['open_fee']),
                (string) $finding['note'],
                (string) ($finding['succession'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param  array{amount: string, currency: string, formatted: string}  $money
     */
    private function plain(array $money): string {
        return str_replace('.', ',', $money['amount']);
    }
}
