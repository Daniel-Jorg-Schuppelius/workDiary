<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureChainContentionCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Audit;

use App\Models\{AuditLog, Organization, User};
use Illuminate\Console\Command;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\{DB, Process};

/**
 * Misst die Sperrkonkurrenz der Audit-Hash-Kette (Vollscan 2026-08-23, A5,
 * MVP-722).
 *
 * `HashChained::applyChainHash()` sperrt den Kettenkopf in `audit_chain_heads`
 * per `lockForUpdate` und hält die Sperre bis zum äußersten Commit. Solange
 * eine Kette je TABELLE geführt wird, serialisiert dieser Kopf alle
 * Organisationen gegeneinander. Das Kommando belegt das mit Zahlen: eine
 * Organisation allein gegen N parallel schreibende Organisationen.
 *
 * **Messwerkzeug, kein Bestand.** Die erzeugten `audit_logs` sind echte
 * Kettenzeilen und append-only — sie lassen sich nicht mehr entfernen. Deshalb
 * nur gegen eine Mess-Datenbank laufen lassen; in der Produktivumgebung
 * verweigert das Kommando ohne `--force` den Dienst.
 */
class MeasureChainContentionCommand extends Command {
    protected $signature = 'audit:measure-chain-contention
        {--orgs=4 : Anzahl parallel schreibender Organisationen}
        {--inserts=500 : Audit-Zeilen je Organisation}
        {--worker : Interner Arbeitsprozess — schreibt und gibt JSON aus}
        {--organization= : Organisation des Arbeitsprozesses}
        {--force : In nicht-lokaler Umgebung erzwingen}';

    protected $description = 'Misst die Sperrkonkurrenz der Audit-Hash-Kette bei 1 vs. N schreibenden Organisationen (MVP-722)';

    public function handle(): int {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('In der Produktivumgebung gesperrt — die Messung schreibt echte, append-only Audit-Zeilen.');

            return self::FAILURE;
        }

        $inserts = max(1, (int) $this->option('inserts'));

        if ($this->option('worker')) {
            return $this->runWorker((int) $this->option('organization'), $inserts);
        }

        $organizations = array_values(array_map('intval', Organization::query()
            ->orderBy('id')
            ->limit(max(2, (int) $this->option('orgs')))
            ->pluck('id')
            ->all()));
        if (count($organizations) < 2) {
            $this->error('Mindestens zwei Organisationen nötig — die Messung vergleicht allein gegen parallel.');

            return self::FAILURE;
        }

        $this->line('<info>Sperrkonkurrenz der Audit-Kette</info> (' . $inserts . ' Zeilen je Organisation)');
        $this->newLine();

        $single = $this->runPool([$organizations[0]], $inserts);
        $this->report('1 Organisation (allein)', $single);

        $parallel = $this->runPool($organizations, $inserts);
        $this->report(count($organizations) . ' Organisationen (parallel)', $parallel);

        $factor = $single['avg_ms'] > 0.0 ? $parallel['avg_ms'] / $single['avg_ms'] : 0.0;
        $this->newLine();
        $this->line(sprintf('  Verlangsamung je Einfügung: %.2f×  (Kette: %s)', $factor, (string) (new AuditLog())->chainName()));
        $this->line(sprintf('  InnoDB-Zeilensperr-Wartevorgänge während des Parallellaufs: %d (%.0f ms gesamt)',
            $parallel['lock_waits'], $parallel['lock_wait_ms']));

        return self::SUCCESS;
    }

    /**
     * Startet je Organisation einen Arbeitsprozess und wartet auf alle.
     *
     * @param  list<int>  $organizations
     * @return array{wall_ms: float, avg_ms: float, max_ms: float, rows: int, failed: int, deadlocks: int, lock_waits: int, lock_wait_ms: float}
     */
    private function runPool(array $organizations, int $inserts): array {
        $before = $this->lockCounters();
        $startedAt = microtime(true);

        $results = Process::pool(function (Pool $pool) use ($organizations, $inserts): void {
            foreach ($organizations as $organizationId) {
                $pool->path(base_path())->timeout(600)->command([
                    PHP_BINARY, 'artisan', 'audit:measure-chain-contention',
                    '--worker', '--organization=' . $organizationId, '--inserts=' . $inserts, '--force',
                ]);
            }
        })->start()->wait();

        $wall = (microtime(true) - $startedAt) * 1000;
        $after = $this->lockCounters();

        $rows = 0;
        $sum = 0.0;
        $max = 0.0;
        $failed = 0;
        $deadlocks = 0;
        foreach ($results as $result) {
            $payload = json_decode(trim((string) $result->output()), true);
            if (! is_array($payload)) {
                $this->warn(sprintf('  Arbeitsprozess Exit %d ohne verwertbare Ausgabe: %s',
                    $result->exitCode() ?? -1,
                    mb_substr(preg_replace('/\s+/', ' ', trim((string) $result->errorOutput())) ?? '', 0, 600)));
                $failed++;

                continue;
            }
            $rows += (int) $payload['rows'];
            $deadlocks += (int) ($payload['deadlocks'] ?? 0);
            $sum += (float) $payload['total_ms'];
            $max = max($max, (float) $payload['max_ms']);
        }

        return [
            'wall_ms' => $wall,
            'avg_ms' => $rows > 0 ? $sum / $rows : 0.0,
            'max_ms' => $max,
            'rows' => $rows,
            'failed' => $failed,
            'deadlocks' => $deadlocks,
            'lock_waits' => $after['waits'] - $before['waits'],
            'lock_wait_ms' => $after['wait_ms'] - $before['wait_ms'],
        ];
    }

    /** @param array{wall_ms: float, avg_ms: float, max_ms: float, rows: int, failed: int, deadlocks: int, lock_waits: int, lock_wait_ms: float} $result */
    private function report(string $label, array $result): void {
        $this->line(sprintf(
            '  %-30s %8.0f ms gesamt  %6.2f ms/Zeile  max %6.1f ms  (%d Zeilen, %d Verklemmungen%s)',
            $label,
            $result['wall_ms'],
            $result['avg_ms'],
            $result['max_ms'],
            $result['rows'],
            $result['deadlocks'],
            $result['failed'] > 0 ? ', ' . $result['failed'] . ' Prozess(e) abgebrochen' : '',
        ));
    }

    /**
     * Zeilensperr-Zähler von InnoDB. MariaDB ≥ 10.6 kennt
     * `information_schema.INNODB_LOCK_WAITS` nicht mehr; die globalen Zähler
     * sind der verbliebene, treiberunabhängige Beleg.
     *
     * @return array{waits: int, wait_ms: float}
     */
    private function lockCounters(): array {
        try {
            $rows = DB::select("SHOW GLOBAL STATUS LIKE 'Innodb_row_lock%'");
        } catch (\Throwable) {
            return ['waits' => 0, 'wait_ms' => 0.0];
        }

        $values = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $values[(string) ($data['Variable_name'] ?? '')] = (float) ($data['Value'] ?? 0);
        }

        return [
            'waits' => (int) ($values['Innodb_row_lock_waits'] ?? 0),
            'wait_ms' => (float) ($values['Innodb_row_lock_time'] ?? 0),
        ];
    }

    /** Arbeitsprozess: schreibt die Zeilen und meldet die Zeiten als JSON. */
    private function runWorker(int $organizationId, int $inserts): int {
        $organization = Organization::query()->find($organizationId);
        if (! $organization instanceof Organization) {
            $this->output->write(json_encode(['rows' => 0, 'total_ms' => 0, 'max_ms' => 0]) ?: '');

            return self::FAILURE;
        }

        $user = User::query()->where('organization_id', $organizationId)->orderBy('id')->first();
        $total = 0.0;
        $max = 0.0;
        $written = 0;

        $deadlocks = 0;
        for ($i = 0; $i < $inserts; $i++) {
            $startedAt = microtime(true);
            try {
                // Wie der Anwendungspfad: audit() läuft in der Transaktion des
                // aufrufenden Dienstes, der Kettenkopf-Lock hält bis zum Commit.
                DB::transaction(static function () use ($organization, $user, $i): void {
                    AuditLog::create([
                        'organization_id' => $organization->id,
                        'user_id' => $user?->id,
                        'event' => 'perf.measure',
                        'auditable_type' => Organization::class,
                        'auditable_id' => $organization->id,
                        'changes' => ['i' => $i],
                    ]);
                });
                $written++;
            } catch (\Throwable) {
                // Der Abbruch IST das Messergebnis: eine Kette je Tabelle
                // lässt gleichzeitige Schreiber auf dem Kettenkopf verklemmen.
                $deadlocks++;
            }
            $elapsed = (microtime(true) - $startedAt) * 1000;
            $total += $elapsed;
            $max = max($max, $elapsed);
        }

        $this->output->write(json_encode([
            'organization' => $organizationId,
            'rows' => $written,
            'deadlocks' => $deadlocks,
            'total_ms' => round($total, 3),
            'max_ms' => round($max, 3),
        ]) ?: '');

        return self::SUCCESS;
    }
}
