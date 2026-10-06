<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatusEnumCastRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „status-Spalte ⇒ Enum-Cast" (Vollscan 2026-08-23, F9):
 * 80 Tabellen führten status/state als freien String (Quote mit ~45 rohen
 * Literalen), obwohl app/Enums 400+ Enums hat; varchar-Längen von 10 bis 64
 * brechen unter MariaDB-Strict beim ersten längeren Wert.
 *
 * Regel: Ein Modell, dessen Tabelle eine Spalte `status` oder `state` hat,
 * castet sie auf eine App\Enums-Klasse. Der Bestand ist abgearbeitet
 * (Konsolidierungs-Audit 2026-10, k3-10, Welle 8); in der BASELINE stehen nur
 * noch Spalten mit Anbieterwerten. Neue Modelle dürfen nicht dazukommen.
 */
class StatusEnumCastRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Modellklassen ohne Enum-Cast (Stand 2026-10-05): nur Anbieterwerte — die
     * Spalte übernimmt ungeprüft, was die fremde API liefert, ein Cast würde
     * das Laden der Zeile bei einem neuen Wert sprengen.
     *
     * @var list<string>
     */
    private const BASELINE = [
        // Billbee-Statuscode (Ganzzahl) aus der Bestell-API; Vorgabe 0 und Codes außerhalb von
        // BillbeeOrderState zeigt stateLabel() als „#<int>".
        'App\Plugins\Billbee\Models\BillbeeOrder',
        // Die Rechnungsprojektion hat noch keine Schreibstelle und keine belegte Wertemenge,
        // der Registrarstatus kommt frei vom Provider.
        'App\Models\Domain\DomainExternalInvoice',
        'App\Models\Domain\DomainProjection',
        // Etsy-Bestellstatus aus der Receipt-Antwort (nullable); der Filter der Liste liest die vorhandenen Werte.
        'App\Plugins\Etsy\Models\EtsyReceipt',
    ];

    /**
     * "Modell::spalte" ohne Enum-Cast (Stand 2026-10-06, Konsolidierungs-Audit
     * 2026-10, k3-10, Runde der benannten Spalten). Die app-eigenen Wertemengen
     * sind gecastet; hier steht nur noch, was die App nicht selbst festlegt
     * oder was kein Status ist. Ein Cast würde das Laden der Zeile beim ersten
     * unbekannten Wert sprengen. Neue Einträge nur mit Begründung.
     *
     * @var list<string>
     */
    private const NAMED_COLUMNS_BASELINE = [
        // Protokoll trägt Rohwerte: Status-Slugs (Status::slug(), das Enum selbst ist int-gestützt)
        // und Altwerte der Datenmigration 2026_08_14 (done/problem/open), die kein heutiger Fall kennt.
        'App\Models\Diary\DiaryEntryEvent::from_status',
        'App\Models\Diary\DiaryEntryEvent::to_status',
        // Rohstatus des Buchhaltungssystems (sevDesk-Code, InvoicePlane-Nummer …) als Nachweis;
        // ausgewertet wird der normalisierte voucher_state (AccountingVoucherState).
        'App\Models\Finance\AccountingVoucher::voucher_status',
        // Freitext bis 24 Zeichen: API und Sync nehmen jede Zeichenkette an, Altbestand trägt eigene
        // Wörter; IdeaNodeStatus ist nur die Vorschlagsliste des Editors.
        'App\Models\Ideas\IdeaNode::node_status',
        // SCORM-Datenmodell (cmi.core.lesson_status, cmi.completion_status, cmi.success_status): der
        // Inhalt meldet den Wert, die App übernimmt ihn ungeprüft; das php-elearning-toolkit hat dafür kein Enum.
        'App\Models\Learning\LearningScormState::lesson_status',
        'App\Models\Learning\LearningScormState::success_status',
        // Kein Status: Schlüssel des zuletzt gespiegelten Halters mit Id (`p:c<id>`, `p:f<id>`, `p:own`, `p:none`).
        'App\Models\Reselling\ResaleSubscription::sync_status',
        // voucherStatus aus der Lexoffice-API, ungeprüft übernommen (das SDK kennt
        // Lexoffice\Enums\VoucherStatus, die Schreibstelle bildet aber nicht darauf ab).
        'App\Plugins\Lexoffice\Models\LexofficeVoucher::voucher_status',
        // Name eines Ticketstatus im Zammad des Kunden, frei konfiguriert (z. B. „closed“).
        'App\Plugins\Zammad\Models\ZammadConnection::resolved_state',
    ];

    public function test_status_columns_are_cast_to_enums(): void {
        $tables = $this->schemaTables();
        $violations = [];
        $resolved = [];

        foreach ($this->modelClasses() as $class) {
            $table = $this->tableOfModel($class);
            if ($table === '' || ! isset($tables[$table])) {
                continue;
            }

            $model = new $class();
            $casts = $model->getCasts();
            foreach (['status', 'state'] as $column) {
                if (! isset($tables[$table]['columns'][$column])) {
                    continue;
                }
                $cast = (string) ($casts[$column] ?? '');
                // Kern-Enums unter app/Enums, Plugin-Enums im Plugin (MVP-1049).
                if ($cast !== '' && enum_exists($cast)) {
                    $resolved[] = $class;
                    continue;
                }
                if (! in_array($class, self::BASELINE, true)) {
                    $violations[] = sprintf('%s — %s.%s ohne Enum-Cast', $class, $table, $column);
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "status-/state-Spalte ohne Enum-Cast (Memory: Enum-Cast nie gegen ->value vergleichen; MariaDB-Strict).\n"
            . "Enum anlegen (Kern: app/Enums, Plugin: app/Plugins/<Plugin>/Enums) und casten.\n\n" . implode("\n", $violations));

        $stale = array_values(array_intersect(self::BASELINE, $resolved));
        $this->assertSame([], $stale, "Aus der BASELINE streichen (inzwischen gecastet):\n" . implode("\n", $stale));
    }

    /**
     * Statusartige Spalten unter anderem Namen (`sync_status`, `payment_state` …)
     * sah die Regel nicht (Konsolidierungs-Audit 2026-10, k3-10). Die Baseline trägt
     * nur begründete Fremd-, Protokoll- und Freitextwerte.
     */
    public function test_named_status_columns_are_cast_to_enums(): void {
        $tables = $this->schemaTables();
        $violations = [];
        $resolved = [];

        foreach ($this->modelClasses() as $class) {
            $table = $this->tableOfModel($class);
            if ($table === '' || ! isset($tables[$table])) {
                continue;
            }
            $casts = (new $class())->getCasts();
            foreach ($tables[$table]['columns'] as $column => $definition) {
                if (preg_match('/_(?:status|state)$/', $column) !== 1 || preg_match('/^(?:var)?char\b/i', $definition) !== 1) {
                    continue;
                }
                $key = $class . '::' . $column;
                $cast = (string) ($casts[$column] ?? '');
                if ($cast !== '' && enum_exists($cast)) {
                    $resolved[] = $key;

                    continue;
                }
                if (! in_array($key, self::NAMED_COLUMNS_BASELINE, true)) {
                    $violations[] = $key;
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Statusartige Spalte ohne Enum-Cast — Enum anlegen und casten:\n" . implode("\n", $violations));

        $stale = array_values(array_intersect(self::NAMED_COLUMNS_BASELINE, $resolved));
        $this->assertSame([], $stale, "Aus NAMED_COLUMNS_BASELINE streichen (inzwischen gecastet):\n" . implode("\n", $stale));
    }
}
