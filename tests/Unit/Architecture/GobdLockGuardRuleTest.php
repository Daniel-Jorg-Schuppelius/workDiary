<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdLockGuardRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Architektur-Gate für GoBD-Festschreibungs-Pfade (gobd-gap-analyse.md,
 * Folge-Roadmap Punkt 3: „keine stillen Updates nach Lock/Storno").
 *
 * Die Unveränderbarkeit festgeschriebener Belege ist als Model-Guard
 * implementiert (`static::updating`/`static::deleting` werfen nach der
 * Festschreibung, bzw. `AppendOnly`/`HashChained` für append-only
 * Nachweise und Ereignisketten). Solche Guards laufen über
 * Eloquent-Model-Events — sie sind daher wirkungslos bei:
 *
 *  - Bulk-Query-Writes (`Invoice::where(…)->update(…)`, `…->delete()`),
 *  - Quiet-Writes (`$invoice->saveQuietly()`, `updateQuietly`, `deleteQuietly`),
 *  - `Model::withoutEvents(…)`,
 *  - rohen Builder-/SQL-Zugriffen (`DB::table('invoices')`, `DB::update('…')`).
 *
 * Dieses Gate hält die Guard-Registrierung in den festschreibungspflichtigen
 * Modellen fest und weist jede neue Guard-Umgehung in `app/` ab. Bewusste,
 * fachlich begründete Umgehungen (z. B. Zahlungsabgleich auf ausgestellten
 * Rechnungen) gehören mit Begründung in die Allow-List.
 *
 * Neue festschreibungspflichtige Modelle (Freeze-Status oder Ereigniskette)
 * werden in GUARDED_MODELS ergänzt — Tabellenname beachten (Default-Naming).
 */
class GobdLockGuardRuleTest extends TestCase {
    /**
     * Festschreibungspflichtige Modelle: Kurzname → [Modell-Datei, Tabelle].
     * Freeze-Guards (booted), AppendOnly-Trait bzw. HashChained (Ketten).
     *
     * @var array<string, array{file: string, table: string}>
     */
    private const GUARDED_MODELS = [
        // Freeze nach Ausstellung/Finalisierung/Übergabe
        'Invoice' => ['file' => 'app/Models/Invoice.php', 'table' => 'invoices'],
        'DatevBookingBatch' => ['file' => 'app/Models/Finance/DatevBookingBatch.php', 'table' => 'datev_booking_batches'],
        'BillingTransfer' => ['file' => 'app/Models/Finance/BillingTransfer.php', 'table' => 'billing_transfers'],
        // Append-only Nachweise (AppendOnly-Trait)
        'StockMovement' => ['file' => 'app/Models/StockMovement.php', 'table' => 'stock_movements'],
        'DiaryEntryEvent' => ['file' => 'app/Models/DiaryEntryEvent.php', 'table' => 'diary_entry_events'],
        'WeatherSnapshot' => ['file' => 'app/Models/WeatherSnapshot.php', 'table' => 'weather_snapshots'],
        'DocumentRenderSnapshot' => ['file' => 'app/Models/DocumentDesign/DocumentRenderSnapshot.php', 'table' => 'document_render_snapshots'],
        'AgileEvent' => ['file' => 'app/Models/Agile/AgileEvent.php', 'table' => 'agile_events'],
        'AssetInspectionEvent' => ['file' => 'app/Models/AssetCompliance/AssetInspectionEvent.php', 'table' => 'asset_inspection_events'],
        'AssetCalibrationCertificate' => ['file' => 'app/Models/AssetCompliance/AssetCalibrationCertificate.php', 'table' => 'asset_calibration_certificates'],
        'CashDailyClosing' => ['file' => 'app/Models/CashDailyClosing.php', 'table' => 'cash_daily_closings'], // Vollaudit 2026-07, H14: verankert die Buchungssperre
        // Vollaudit 2026-07 (M56): ISMS-Freeze-Guards (046 — finalisiert/genehmigt = eingefroren)
        'IsmsAuditPackage' => ['file' => 'app/Models/Isms/IsmsAuditPackage.php', 'table' => 'isms_audit_packages'],
        'IsmsRiskAssessment' => ['file' => 'app/Models/Isms/IsmsRiskAssessment.php', 'table' => 'isms_risk_assessments'],
        // Vollaudit 2026-07 (M52): GoBD-nahe Nachweis-Events, jetzt mit AppendOnly-Trait
        'MonthClosureEvent' => ['file' => 'app/Models/MonthClosureEvent.php', 'table' => 'month_closure_events'],
        'TimeExportEvent' => ['file' => 'app/Models/TimeExportEvent.php', 'table' => 'time_export_events'],

        // HashChained-Ereignisketten (append-only, Hash-Kette)
        'CashEntry' => ['file' => 'app/Models/CashEntry.php', 'table' => 'cash_entries'], // MVP-414 Kassenbuch
        'AuditLog' => ['file' => 'app/Models/AuditLog.php', 'table' => 'audit_logs'],
        'OrganizationAuditLog' => ['file' => 'app/Models/OrganizationAuditLog.php', 'table' => 'organization_audit_logs'],
        'BillingTransferEvent' => ['file' => 'app/Models/Finance/BillingTransferEvent.php', 'table' => 'billing_transfer_events'],
        'DatevBookingEvent' => ['file' => 'app/Models/Finance/DatevBookingEvent.php', 'table' => 'datev_booking_events'],
        'PaymentReconciliationEvent' => ['file' => 'app/Models/Finance/PaymentReconciliationEvent.php', 'table' => 'payment_reconciliation_events'],
        'CaseEvent' => ['file' => 'app/Models/Whistleblowing/CaseEvent.php', 'table' => 'case_events'],
        'RequestEvent' => ['file' => 'app/Models/Privacy/RequestEvent.php', 'table' => 'request_events'],
        'IncidentEvent' => ['file' => 'app/Models/Privacy/IncidentEvent.php', 'table' => 'incident_events'],
    ];

    /**
     * Quiet-Write-Empfänger, die auf ein geschütztes Modell hindeuten
     * (Variablen-Heuristik; Treffer erfordern Umbau oder Allow-List-Eintrag).
     *
     * @var array<int, string>
     */
    private const QUIET_RECEIVERS = [
        'invoice',
        'transfer',
        'billingTransfer',
        'batch',
        'movement',
        'stockMovement',
        'auditLog',
        // Vollaudit 2026-07 (M56): Empfänger der neueren Registry-Einträge.
        'cashEntry',
        'package',
        'assessment',
    ];

    /**
     * Schreibmethoden, die innerhalb eines Query-Statements auf einem
     * geschützten Modell die Model-Events (und damit den Guard) umgehen.
     *
     * @var array<int, string>
     */
    private const BULK_WRITE_MARKERS = [
        '->update(',
        '->updateQuietly(',
        '->delete(',
        '->forceDelete(',
        '->upsert(',
        '->insert(',
    ];

    /**
     * Bewusste, fachlich begründete Guard-Umgehungen (Datei → Begründung).
     *
     * @var array<string, string>
     */
    private const ALLOW_LIST = [
        // Zahlungsabgleich schreibt Zahl-/Abgleichsfelder auf AUSGESTELLTEN
        // Rechnungen — bewusste Umgehung des Ausstellungs-Guards, dokumentiert
        // in Invoice.php (MUTABLE_AFTER_ISSUE/saveQuietly-Hinweis).
        'app/Services/Finance/ReconciliationService.php' => 'Zahlungsabgleich aktualisiert Zahlfelder ausgestellter Rechnungen (dokumentierte Guard-Ausnahme).',
        // Retainer-Voucher-Abgleich (Feature 098) markiert Pauschal-Rechnungen
        // nach Lexoffice-Rückmeldung als bezahlt — schreibt ausschließlich die
        // MUTABLE_AFTER_ISSUE-Whitelist (status, paid_on), identisch zum
        // ReconciliationService-Fall oben.
        'app/Services/Billing/RetainerVoucherReconciler.php' => 'Retainer-Zahlungsabgleich aktualisiert status/paid_on ausgestellter Pauschal-Rechnungen (dokumentierte Guard-Ausnahme).',
    ];

    public function test_guarded_models_register_lock_guards(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $missing = [];

        foreach (self::GUARDED_MODELS as $name => $model) {
            $source = (string) file_get_contents($root . '/' . $model['file']);

            $appendOnly = str_contains($source, 'use AppendOnly;') || str_contains($source, 'use HashChained;');
            $freezeGuard = str_contains($source, 'static::updating(') && str_contains($source, 'static::deleting(');

            if (! $appendOnly && ! $freezeGuard) {
                $missing[] = $name . ' (' . $model['file'] . ')';
            }
        }

        $this->assertSame([], $missing, sprintf(
            "Festschreibungspflichtiges Modell ohne Lock-Guard (weder AppendOnly/HashChained noch static::updating+deleting):\n%s\n"
                . 'Guard nachrüsten (Muster: AppendOnly-Trait bzw. Invoice/DatevBookingBatch) oder Registry-Eintrag begründet entfernen.',
            implode("\n", $missing),
        ));
    }

    public function test_no_guard_bypassing_writes_on_locked_models(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $violations = [];

        foreach ($this->phpFiles($root . DIRECTORY_SEPARATOR . 'app') as $file) {
            $relative = str_replace([$root . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $file);

            if (array_key_exists($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripCommentLines((string) file_get_contents($file));

            foreach ($this->bypassFindings($source) as [$line, $snippet]) {
                $violations[] = "$relative:$line — $snippet";
            }
        }

        sort($violations);

        $this->assertSame([], $violations, sprintf(
            "Guard-umgehender Schreibzugriff auf festgeschriebene Modelle/Tabellen gefunden (GoBD-Festschreibung).\n"
                . "Über das Modell schreiben (Guard greift) oder die Stelle mit fachlicher Begründung in die Allow-List eintragen:\n%s",
            implode("\n", $violations),
        ));
    }

    /**
     * @return list<array{int, string}>
     */
    private function bypassFindings(string $source): array {
        $findings = [];

        foreach (self::GUARDED_MODELS as $name => $model) {
            // Model::withoutEvents(…) auf geschütztem Modell.
            $this->collectOccurrences($source, $name . '::withoutEvents(', $findings);

            // Bulk-Query-Writes: X::where/query/whereIn(…)…->update|delete|…
            foreach ([$name . '::where(', $name . '::whereIn(', $name . '::query(', $name . '::whereRaw('] as $entry) {
                $offset = 0;
                while (($pos = strpos($source, $entry, $offset)) !== false) {
                    // Wortgrenze davor: kein Treffer in längeren Klassennamen.
                    $before = $pos > 0 ? $source[$pos - 1] : ' ';
                    $offset = $pos + strlen($entry);
                    if (preg_match('/[A-Za-z0-9_\\\\]/', $before) === 1) {
                        continue;
                    }

                    $statementEnd = strpos($source, ';', $pos);
                    $statement = substr($source, $pos, ($statementEnd === false ? strlen($source) : $statementEnd) - $pos);

                    foreach (self::BULK_WRITE_MARKERS as $marker) {
                        if (str_contains($statement, $marker)) {
                            $findings[] = [$this->lineOf($source, $pos), trim(strtok($statement, "\n")) . ' … ' . $marker . ')'];
                            break;
                        }
                    }
                }
            }

            // Roh-Zugriffe auf die Tabelle.
            foreach (["DB::table('{$model['table']}'", "DB::table(\"{$model['table']}\""] as $needle) {
                $this->collectOccurrences($source, $needle, $findings);
            }
        }

        // Quiet-Writes auf typischen Empfängern geschützter Modelle.
        if (preg_match_all(
            '/\$(' . implode('|', self::QUIET_RECEIVERS) . ')->(saveQuietly|updateQuietly|deleteQuietly)\(/',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
        ) > 0) {
            foreach ($matches[0] as [$match, $pos]) {
                $findings[] = [$this->lineOf($source, (int) $pos), $match . '…)'];
            }
        }

        return $findings;
    }

    /**
     * @param  list<array{int, string}>  $findings
     */
    private function collectOccurrences(string $source, string $needle, array &$findings): void {
        $offset = 0;
        while (($pos = strpos($source, $needle, $offset)) !== false) {
            $findings[] = [$this->lineOf($source, $pos), $needle . '…)'];
            $offset = $pos + strlen($needle);
        }
    }

    /** Entfernt reine Kommentarzeilen (//, *, /*), erhält Zeilennummern. */
    private function stripCommentLines(string $source): string {
        $lines = explode("\n", $source);

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                $lines[$i] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function lineOf(string $source, int $offset): int {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $dir): iterable {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
