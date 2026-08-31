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

use App\Support\Gobd\GobdLockRegistry;
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
 * werden in {@see GobdLockRegistry::MODELS} ergänzt (app-seitig seit MVP-699,
 * weil die Verfahrensdokumentation die Liste zur Laufzeit ausweist) —
 * Tabellenname beachten (Default-Naming).
 */
class GobdLockGuardRuleTest extends TestCase {
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
        // MVP-702: Fahrtenbuch-Festschreibung.
        'travelLog',
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
        // Messdatensatz des Lastprofils (MVP-683): schreibt Buchungen per
        // Sammel-Insert, weil 30.000 Einzelbuchungen über den JournalService
        // die Messung selbst zum Engpass machen würden. Das Kommando ist ein
        // Werkzeug, kein Bestand — es verweigert in der Produktivumgebung den
        // Dienst und weist am Ende darauf hin, dass die erzeugten Buchungen
        // ohne Nachweis-Snapshot und ohne Ereignisse entstehen.
        'app/Console/Commands/Finance/SeedAccountingLoadCommand.php' => 'Messdatensatz des Lastprofils, nur außerhalb der Produktivumgebung (MVP-683).',
        // Messdatensatz der Listen-/Index-Lastprofile (MVP-722): schreibt
        // travel_logs und audit_logs per Sammel-Insert, weil 100.000 Zeilen
        // über die Modelle die Messung selbst zum Engpass machen würden — bei
        // audit_logs käme zusätzlich der Kettenkopf-Lock dazwischen. Dasselbe
        // Muster wie beim Buchhaltungs-Lastprofil: das Kommando verweigert in
        // der Produktivumgebung den Dienst und weist darauf hin, dass die
        // erzeugten Audit-Zeilen bewusst keine Hash-Kette tragen.
        'app/Console/Commands/Support/SeedQueryLoadCommand.php' => 'Messdatensatz der Listen-Lastprofile, nur außerhalb der Produktivumgebung (MVP-722).',
    ];

    public function test_guarded_models_register_lock_guards(): void {
        $root = (string) realpath(__DIR__ . '/../../..');
        $missing = [];

        foreach (GobdLockRegistry::MODELS as $name => $model) {
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

    /**
     * Jede hash-verkettete Tabelle gehört ins Gate (Sicherheitsscan
     * 2026-08-23, S-59).
     *
     * `config('audit.chains')` und die Registry beschreiben dasselbe
     * Versprechen aus zwei Richtungen: „diese Zeilen sind unveränderlich".
     * Wer eine neue Kette einträgt und die Registry vergisst, bekommt eine
     * Kette ohne Schreibschutz-Prüfung — und merkt es nie.
     */
    public function test_jede_audit_kette_steht_in_der_registry(): void {
        // Die Konfiguration direkt lesen, nicht über `config()`: dieser Test
        // läuft ohne gebootete Anwendung, wie seine Geschwister im selben
        // Verzeichnis.
        $config = require (string) realpath(__DIR__ . '/../../..') . '/config/audit.php';
        $chains = is_array($config['chains'] ?? null) ? $config['chains'] : [];

        $tables = array_column(GobdLockRegistry::MODELS, 'table');
        $missing = [];

        foreach (array_keys($chains) as $table) {
            if (! in_array((string) $table, $tables, true)) {
                $missing[] = (string) $table;
            }
        }

        sort($missing);

        $this->assertSame([], $missing, sprintf(
            "Hash-verkettete Tabellen ohne Eintrag in GobdLockRegistry::MODELS:\n  %s",
            implode("\n  ", $missing),
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

        foreach (GobdLockRegistry::MODELS as $name => $model) {
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
