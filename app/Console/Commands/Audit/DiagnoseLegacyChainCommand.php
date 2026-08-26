<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiagnoseLegacyChainCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Audit;

use App\Models\Concerns\HashChainable;
use App\Services\Audit\AuditChainVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnose der ALTEN Tabellen-Verkettung vor der Umkettung je Organisation
 * (Migration 2027_02_19_103100, MVP-722).
 *
 * Warum ein eigenes Kommando: `audit:verify` prüft gegen die AKTUELLE
 * {@see HashChainable::chainName()} — nach dem Code-Deploy ist das bereits
 * `tabelle:organisation`. Solange der Bestand noch der Tabellenkette folgt,
 * meldet es deshalb zwangsläufig einen Bruch an der ersten Zeile, ohne dass
 * etwas mit den Daten wäre. Dieses Kommando prüft die Verkettung, die im
 * Bestand tatsächlich steht.
 *
 * Zwei Befunde, die streng auseinanderzuhalten sind:
 *  - **Zeiger-Riss** (`prev_hash` zeigt nicht auf die Vorgängerzeile): die
 *    Reihenfolge ist gerissen. Häufigste betriebliche Ursache ist genau der
 *    Fehler, den die Umkettung behebt — bei der globalen Kette brachen
 *    parallele Einfügungen als Verklemmung ab (`audit:measure-chain-contention`
 *    belegt 445 von 900), wobei der Kettenkopf und die geschriebene Zeile
 *    auseinanderlaufen konnten. Der INHALT ist davon unberührt.
 *  - **Inhalts-Änderung** (Zeilen-Hash stimmt nicht über dem GESPEICHERTEN
 *    `prev_hash`): die Zeile wurde nach dem Schreiben verändert. Das ist ein
 *    echter GoBD-Befund und keine Betriebsstörung.
 *
 * Das Kommando ändert NICHTS. Es liest und berichtet.
 */
class DiagnoseLegacyChainCommand extends Command {
    protected $signature = 'audit:diagnose-legacy-chain
        {--chain= : Nur diese Tabelle prüfen}
        {--limit=25 : Höchstens so viele Fundstellen je Tabelle ausgeben}';

    protected $description = 'Prüft die ALTE Tabellen-Verkettung der Audit-Tabellen und trennt Zeiger-Risse von Inhalts-Änderungen (vor der Umkettung je Organisation).';

    public function handle(AuditChainVerifier $verifier): int {
        $only = $this->option('chain');
        $limit = max(1, (int) $this->option('limit'));
        $chains = $verifier->chains();

        if ($only !== null && ! isset($chains[$only])) {
            $this->error("Unbekannte Kette: {$only}. Erlaubt: " . implode(', ', array_keys($chains)));

            return self::INVALID;
        }

        $tampered = false;
        $gaps = false;

        foreach ($chains as $table => $modelClass) {
            if ($only !== null && $only !== $table) {
                continue;
            }
            if (! DB::getSchemaBuilder()->hasTable($table) || ! DB::table($table)->exists()) {
                $this->line("[{$table}] leer oder nicht vorhanden — nichts zu prüfen.");

                continue;
            }

            $report = $this->inspect($table, $modelClass);
            $this->report($table, $report, $limit);

            $tampered = $tampered || $report['rowHashFailures'] !== [];
            $gaps = $gaps || $report['pointerGaps'] !== [];
        }

        $this->newLine();
        if ($tampered) {
            $this->error('Befund: mindestens eine Zeile wurde nach dem Schreiben verändert. Das ist KEIN Betriebsfehler — vor jeder Umkettung fachlich klären.');

            return self::FAILURE;
        }
        if ($gaps) {
            $this->warn('Befund: nur Zeiger-Risse, alle Zeilen-Hashes stimmen über ihrem gespeicherten prev_hash.');
            $this->line('Der Inhalt ist damit unverändert; gerissen ist die Reihenfolge-Verkettung.');
            $this->line('Die Umkettung darf laufen, wenn die Risse bewusst freigegeben werden:');
            $this->line('  AUDIT_RECHAIN_ALLOW_POINTER_GAPS=1 php artisan migrate --force');
            $this->line('Die Fundstellen landen dann im JSONL-Nachweis der Migration.');

            return self::FAILURE;
        }

        $this->info('Alle geprüften Ketten sind unter der alten Verkettung lückenlos und unverändert.');

        return self::SUCCESS;
    }

    /**
     * Läuft EINMAL über die Tabelle und sammelt beide Befundarten getrennt.
     *
     * @param  class-string<HashChainable>  $modelClass
     * @return array{rows: int, pointerGaps: list<array{id: int, expected: ?string, found: ?string}>, rowHashFailures: list<int>}
     */
    private function inspect(string $table, string $modelClass): array {
        $expectedPrev = null;
        $rows = 0;
        $pointerGaps = [];
        $rowHashFailures = [];

        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            $rows++;

            if ($row->prev_hash !== $expectedPrev) {
                $pointerGaps[] = ['id' => (int) $row->id, 'expected' => $expectedPrev, 'found' => $row->prev_hash];
            }

            // Entscheidend: gegen den GESPEICHERTEN prev_hash rechnen, nicht
            // gegen den erwarteten. Nur so trennt sich „Inhalt verändert" von
            // „Verkettung gerissen" — sonst schleppt ein Riss alle Folgezeilen
            // als vermeintliche Fälschungen mit.
            $model = $modelClass::fromStorageRow((array) $row);
            $expectedHash = $modelClass::chainHash($row->prev_hash, $model->hashPayload());
            if (! hash_equals($expectedHash, (string) $row->hash)) {
                $rowHashFailures[] = (int) $row->id;
            }

            $expectedPrev = (string) $row->hash;
        }

        return ['rows' => $rows, 'pointerGaps' => $pointerGaps, 'rowHashFailures' => $rowHashFailures];
    }

    /**
     * @param  array{rows: int, pointerGaps: list<array{id: int, expected: ?string, found: ?string}>, rowHashFailures: list<int>}  $report
     */
    private function report(string $table, array $report, int $limit): void {
        $this->newLine();
        $this->line("<options=bold>[{$table}]</> {$report['rows']} Zeilen geprüft.");

        if ($report['pointerGaps'] === [] && $report['rowHashFailures'] === []) {
            $this->info('  lückenlos und unverändert.');

            return;
        }

        if ($report['rowHashFailures'] !== []) {
            $shown = array_slice($report['rowHashFailures'], 0, $limit);
            $this->error('  Inhalts-Änderung bei ' . count($report['rowHashFailures']) . ' Zeile(n): ' . implode(', ', $shown)
                . (count($report['rowHashFailures']) > $limit ? ' …' : ''));
        }

        if ($report['pointerGaps'] !== []) {
            $this->warn('  Zeiger-Risse: ' . count($report['pointerGaps']) . ' Fundstelle(n).');
            foreach (array_slice($report['pointerGaps'], 0, $limit) as $gap) {
                $this->line(sprintf('    id=%d  erwartet=%s  gespeichert=%s',
                    $gap['id'],
                    $this->short($gap['expected']),
                    $this->short($gap['found'])));
            }
            if (count($report['pointerGaps']) > $limit) {
                $this->line('    … (' . (count($report['pointerGaps']) - $limit) . ' weitere)');
            }
        }
    }

    private function short(?string $hash): string {
        return $hash === null ? '(Kettenanfang)' : substr($hash, 0, 12) . '…';
    }
}
