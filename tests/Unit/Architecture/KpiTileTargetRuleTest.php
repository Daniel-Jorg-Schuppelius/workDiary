<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KpiTileTargetRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Eine Zählkachel führt zu dem, was sie zählt“
 * (ux-pattern-katalog §4, Pilot Reselling 2026-11-01, Vollscan 2026-11-02
 * gap-06): jede `<x-kpi-tile>` mit `value` trägt ein `href` — auf einer
 * Listenseite den Filter der eigenen Route (`:active`, wenn er gesetzt ist),
 * sonst die Liste hinter der Zahl; Berichtskacheln springen auf den
 * Drilldown-Abschnitt derselben Seite.
 *
 * Ohne Ziel bleiben nur Kacheln ohne adressierbare Liste: Vorschauen nach
 * einem POST (Importvorschau), Mess- und Summenwerte ohne eigene Zeilen,
 * Kennzahlen in Dialogen. Diese stehen mit Grund in ALLOW_LIST; einen
 * Restbestand ohne Begründung gibt es seit der Umstellung 2026-11-02 nicht,
 * neue Fundstellen sind Verstöße.
 */
class KpiTileTargetRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Datei → [Kacheln ohne Ziel, Grund]. Nur Kacheln ohne adressierbare
     * Liste; ein Zähler über Zeilen bekommt ein Ziel, keinen Eintrag.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    private const array ALLOW_LIST = [
        'resources/views/admin/ui-patterns.blade.php' => [4, 'Musterseite mit Beispielwerten.'],
        'resources/views/articles/calculation/scheme.blade.php' => [2, 'Kalkulationsparameter (Lohn, Stundensatz) ohne Zeilen.'],
        'resources/views/articles/calculation/show.blade.php' => [4, 'Kalkulationsergebnis (Kosten, Preis, Lohnanteil) ohne Zeilen.'],
        'resources/views/articles/costing.blade.php' => [5, 'Mess- und Durchschnittswerte der Nachkalkulation; die Aufträge verlinkt die erste Kachel.'],
        'resources/views/articles/footprint.blade.php' => [3, 'CO₂-Messwerte ohne Zeilen.'],
        'resources/views/asset-compliance/reports.blade.php' => [4, 'Prüfquote und Prüfkosten (Beträge) ohne Einzelliste.'],
        'resources/views/asset-finance/reports.blade.php' => [3, 'Summen des Ratenplans (Beträge).'],
        'resources/views/asset-finance/show.blade.php' => [3, 'Summen des Ratenplans direkt über der Tabelle (Beträge).'],
        'resources/views/assets/show.blade.php' => [3, 'Offene Issues des Assets haben keine eigene Liste (der Berichts-Drilldown braucht Berichtsrecht und Zeitraum); die Verknüpfungssumme fasst vier Abschnitte.'],
        'resources/views/billing/feed.blade.php' => [5, 'Summen (Umsatz, Ausgaben, Saldo, intern) und der Zähler ohne Betrag haben keinen Filter.'],
        'resources/views/buildings/show.blade.php' => [2, 'Flächenwerte (m²).'],
        'resources/views/cash-registers/show.blade.php' => [1, 'Kassensaldo (Betrag).'],
        'resources/views/claims/reports.blade.php' => [2, 'Ø Dauer und Kosten (Messwerte).'],
        'resources/views/crisis/index.blade.php' => [1, 'Überfällige Maßnahmen haben keine fallübergreifende Liste.'],
        'resources/views/customer/billing/show.blade.php' => [4, 'Abrechnungsblock einer Abrechnung: Summen ohne Zeilen.'],
        'resources/views/customer/insights/index.blade.php' => [1, 'Ø Bearbeitungstage (Messwert).'],
        'resources/views/customer/time-entries/index.blade.php' => [3, 'Kennzahlen der gefilterten Liste darunter (Summe, Einträge, Projekte).'],
        'resources/views/customers/show.blade.php' => [2, 'Umsatz und Gewinn (Beträge).'],
        'resources/views/damage/index.blade.php' => [2, 'Schätz- und Regulierungssummen (Beträge).'],
        'resources/views/disposal/reports.blade.php' => [3, 'Geräte und Gewichte (Mengen).'],
        'resources/views/energy-logs/index.blade.php' => [4, 'Summen der gefilterten Energieliste.'],
        'resources/views/expenses/index.blade.php' => [4, 'Spesensummen (Beträge).'],
        'resources/views/finance/accounting/fixed-assets.blade.php' => [1, 'Buchwert (Betrag).'],
        'resources/views/finance/accounting/open-items.blade.php' => [1, 'Altersklassen-Summen (Beträge); der Postendienst paginiert ohne Klassenfilter.'],
        'resources/views/finance/datev/index.blade.php' => [1, 'Exportsumme des Jahres (Betrag).'],
        'resources/views/finance/dunning/index.blade.php' => [1, 'Offene Summe (Betrag).'],
        'resources/views/finance/open-times/index.blade.php' => [2, 'Minuten und Betrag der Liste (Summen).'],
        'resources/views/finance/resale/import_preview.blade.php' => [4, 'Importvorschau nach POST — keine adressierbare Liste.'],
        'resources/views/flex/index.blade.php' => [6, 'Zeitkonto-Messwerte (Soll, Ist, Saldo, Kontostand, Anwesenheitstage).'],
        'resources/views/floors/show.blade.php' => [2, 'Fläche und Kapazität (Messwerte).'],
        'resources/views/inventory/recalls/show.blade.php' => [1, 'Rücklaufquote.'],
        'resources/views/investments/show.blade.php' => [4, 'Soll-Ist-Beträge der Investition.'],
        'resources/views/per-diem-trips/index.blade.php' => [1, 'Pauschalen-Summe (Betrag).'],
        'resources/views/per-diem-trips/show.blade.php' => [3, 'Pauschale, Land und 3-Monats-Regel der Reise (Werte ohne Zeilen).'],
        'resources/views/presence/board.blade.php' => [1, 'Nicht eingestempelte Personen haben keinen Abschnitt auf der Tafel.'],
        'resources/views/projects/_time_tab.blade.php' => [3, 'Zeitsummen des Projekts; der Reiter ist die Liste.'],
        'resources/views/quotes/win-rate.blade.php' => [3, 'Quoten und Volumen (Beträge).'],
        'resources/views/rental/reports.blade.php' => [4, 'Auslastung, Umsatz, Gerätetage und Blockaden (Messwerte).'],
        'resources/views/reports/absence-card.blade.php' => [4, 'Urlaubskonto einer Person (Tage) ohne Zeilen.'],
        'resources/views/reports/absences.blade.php' => [4, 'Abwesenheitssummen (Tage, Minuten).'],
        'resources/views/reports/accounting/account-ledger.blade.php' => [2, 'Anfangs- und Endsaldo (Beträge).'],
        'resources/views/reports/accounting/budget.blade.php' => [2, 'Budgetsumme und Jahr.'],
        'resources/views/reports/accounting/bwa.blade.php' => [3, 'BWA-Zwischensummen (Beträge).'],
        'resources/views/reports/accounting/euer.blade.php' => [4, 'EÜR-Summen (Beträge).'],
        'resources/views/reports/accounting/liquidity-forecast.blade.php' => [4, 'Liquiditätsvorschau (Beträge).'],
        'resources/views/reports/accounting/profit-and-loss.blade.php' => [3, 'Ergebnisrechnung (Beträge).'],
        'resources/views/reports/accounting/replacement-forecast.blade.php' => [1, 'Ersatzbedarf je Jahr (Beträge).'],
        'resources/views/reports/accounting/vat.blade.php' => [5, 'USt-Summen (Beträge).'],
        'resources/views/reports/allocations.blade.php' => [1, 'Summe der aufgeteilten Zeit.'],
        'resources/views/reports/attendance.blade.php' => [4, 'Zeitsummen (Soll, Anwesend, Gebucht, Saldo).'],
        'resources/views/reports/cohort-comparison.blade.php' => [4, 'Statistische Kennzahlen des Kohortenvergleichs.'],
        'resources/views/reports/coverage.blade.php' => [3, 'Personentage und Erfüllungsquote.'],
        'resources/views/reports/customer-retention.blade.php' => [3, 'Quote, Ø Kundenalter und aktive Kunden am Periodenende ohne eigene Liste.'],
        'resources/views/reports/customer-value.blade.php' => [4, 'Erlössumme und Konzentrationskennzahlen.'],
        'resources/views/reports/expenses.blade.php' => [1, 'Spesensumme (Betrag).'],
        'resources/views/reports/fleet.blade.php' => [4, 'Kilometer, Energie und Kosten (Messwerte).'],
        'resources/views/reports/index.blade.php' => [1, 'Ø Stunden pro Tag (Messwert).'],
        'resources/views/reports/investments.blade.php' => [3, 'Investitionssummen (Beträge).'],
        'resources/views/reports/logbook.blade.php' => [3, 'Kilometer je Fahrtart und Privatanteil (Messwerte).'],
        'resources/views/reports/materials.blade.php' => [1, 'Materialsumme (Betrag).'],
        'resources/views/reports/my-year.blade.php' => [1, 'Jahressumme (Stunden).'],
        'resources/views/reports/on-call.blade.php' => [3, 'Bereitschafts- und Einsatzminuten, Aktiv-Anteil.'],
        'resources/views/reports/operations.blade.php' => [1, 'Servicezeit (Summe).'],
        'resources/views/reports/payment-behavior.blade.php' => [3, 'DSO, Ø Zahldauer und Pünktlichkeitsquote.'],
        'resources/views/reports/plan-ist/presence.blade.php' => [3, 'Plan-/Ist-Minuten (Summen).'],
        'resources/views/reports/plan-ist/projects.blade.php' => [4, 'Plan-/Ist-Minuten (Summen).'],
        'resources/views/reports/plan-ist/shifts.blade.php' => [4, 'Plan-/Ist-Minuten und Abdeckung (Summen, Quote).'],
        'resources/views/reports/plan-ist/sites.blade.php' => [1, 'Ist-Minuten (Summe).'],
        'resources/views/reports/plan-ist/summary.blade.php' => [3, 'Plan-/Ist-Minuten (Summen).'],
        'resources/views/reports/procedure-deviations.blade.php' => [2, 'Follow-up-Quote und Ø Entscheidungsdauer.'],
        'resources/views/reports/product-revenue.blade.php' => [3, 'Umsatzsummen und Anteil ohne Artikelbezug.'],
        'resources/views/reports/sickness.blade.php' => [4, 'Krankheitssummen (Tage, Fälle).'],
        'resources/views/reports/sla.blade.php' => [1, 'Erfüllungsquote.'],
        'resources/views/reports/supplier-value.blade.php' => [4, 'Ausgabensumme und Konzentrationskennzahlen.'],
        'resources/views/reports/suppliers.blade.php' => [4, 'Ausgabensumme, Konzentration und offener Betrag.'],
        'resources/views/reports/tender-cockpit.blade.php' => [2, 'Trefferquote und gewonnener Wert.'],
        'resources/views/reports/tour-plan-actual.blade.php' => [3, 'Ø Abweichungen und Verspätungsanteil.'],
        'resources/views/reports/training.blade.php' => [1, 'Erfüllungsquote.'],
        'resources/views/reports/utilization.blade.php' => [5, 'Auslastungsquoten und Zeitsummen.'],
        'resources/views/reports/work-balance.blade.php' => [5, 'Zeitbilanz (Summen).'],
        'resources/views/sales/commission-runs/show.blade.php' => [2, 'Summen des Provisionslaufs (Beträge).'],
        'resources/views/sites/show.blade.php' => [1, 'Fläche (m²).'],
        'resources/views/suppliers/show.blade.php' => [1, 'Artikel-Bezugsquellen haben keine Liste je Lieferant.'],
        'resources/views/sustainability/index.blade.php' => [2, 'CO₂e-Summe und Schätzanteil (Messwerte).'],
        'resources/views/timesheets/show.blade.php' => [3, 'Summen des Stundenzettels.'],
        'resources/views/today/show.blade.php' => [1, 'Soll-Minuten des Tages (Messwert).'],
        'resources/views/travel-logs/index.blade.php' => [2, 'Kilometer und Erstattung (Summen).'],
    ];

    public function test_every_counting_tile_has_a_target(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = $this->countTilesWithoutTarget($source);
            $seen[$relative] = true;

            $allowed = self::ALLOW_LIST[$relative][0] ?? 0;
            if ($count > $allowed) {
                $violations[] = sprintf('%s — %d Kachel(n) ohne href, erlaubt %d', $relative, $count, $allowed);
            } elseif ($count < $allowed) {
                $stale[] = sprintf("'%s' => %d, // aktuell %d", $relative, $allowed, $count);
            }
        }

        foreach (array_keys(self::ALLOW_LIST) as $relative) {
            if (! isset($seen[$relative])) {
                $stale[] = sprintf("'%s' — Datei existiert nicht mehr, Eintrag streichen", $relative);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Zählkachel ohne Ziel (ux-pattern-katalog §4): <x-kpi-tile> mit value braucht :href —\n"
            . "auf einer Listenseite den Filter der eigenen Route (:active, wenn gesetzt), sonst die Liste hinter der Zahl;\n"
            . "Berichte springen auf den Drilldown-Abschnitt (#…). Nur Kacheln ohne adressierbare Liste (Importvorschau,\n"
            . "Messwert, Summe im Dialog) stehen mit Grund in KpiTileTargetRuleTest::ALLOW_LIST.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Eintrag deckt keine Kachel mehr — in KpiTileTargetRuleTest::ALLOW_LIST abtragen:\n"
            . implode("\n", $stale));
    }

    /** Zählt `<x-kpi-tile>`-Tags mit value-, aber ohne href-Attribut; Attributwerte dürfen `>` enthalten. */
    private function countTilesWithoutTarget(string $source): int {
        $count = 0;
        $offset = 0;
        while (($start = strpos($source, '<x-kpi-tile', $offset)) !== false) {
            $tag = $this->tagAt($source, $start);
            $offset = $start + strlen($tag);
            if (preg_match('/(?<![\w:-])(?::?value)=/', $tag) === 1 && preg_match('/(?<![\w:-])(?::?href)=/', $tag) !== 1) {
                $count++;
            }
        }

        return $count;
    }

    /** Der Start-Tag ab `$start` bis zum schließenden `>` außerhalb von Anführungszeichen. */
    private function tagAt(string $source, int $start): string {
        $length = strlen($source);
        $quote = null;
        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                return substr($source, $start, $i - $start + 1);
            }
        }

        return substr($source, $start);
    }
}
