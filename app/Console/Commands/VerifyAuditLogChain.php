<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VerifyAuditLogChain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Concerns\HashChainable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prüft die Unveränderbarkeit der Audit-Ketten (GoBD), indem die SHA-256-Hash-
 * Kette je Tabelle in id-Reihenfolge nachgerechnet wird. Gemeldet werden:
 *   - ein nicht passender Zeilen-Hash  → Zeile wurde nachträglich verändert
 *   - ein gebrochener prev_hash-Verweis → Zeile gelöscht/eingeschoben
 * Exit-Code 1, sobald eine Kette an einer Stelle bricht (CI-/Cron-tauglich).
 *
 * Der Hash wird über das jeweilige Modell ({@see Model::hashPayload()})
 * berechnet – identisch zum Schreibpfad und damit treiberunabhängig.
 */
class VerifyAuditLogChain extends Command {
    protected $signature = 'audit:verify {--chain= : Nur diese Kette prüfen (audit_logs|organization_audit_logs)}';

    protected $description = 'Prüft die Integrität der Audit-Hash-Ketten (GoBD-Unveränderbarkeit).';

    public function handle(): int {
        $chains = $this->chains();

        $only = $this->option('chain');
        if ($only !== null && ! isset($chains[$only])) {
            $this->error("Unbekannte Kette: {$only}. Erlaubt: " . implode(', ', array_keys($chains)));

            return self::INVALID;
        }

        $exit = self::SUCCESS;
        foreach ($chains as $table => $modelClass) {
            if ($only !== null && $only !== $table) {
                continue;
            }
            if ($this->verifyChain($table, $modelClass) !== self::SUCCESS) {
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }

    /** @return array<string, class-string<HashChainable>> */
    private function chains(): array {
        $result = [];
        foreach ((array) config('audit.chains', []) as $table => $class) {
            if (is_string($table) && is_string($class) && is_a($class, HashChainable::class, true)) {
                $result[$table] = $class;
            }
        }

        return $result;
    }

    /** @param class-string<HashChainable> $modelClass */
    private function verifyChain(string $table, string $modelClass): int {
        $expectedPrev = null;
        $checked = 0;

        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            if ($row->prev_hash !== $expectedPrev) {
                $this->error("[{$table}] Integritätsverletzung bei id={$row->id}: prev_hash verweist nicht auf die Vorgängerzeile (Eintrag gelöscht oder eingeschoben).");
                $this->line("  Geprüft bis zum Bruch: {$checked} Einträge.");

                return self::FAILURE;
            }

            $model = $modelClass::fromStorageRow((array) $row);
            $expectedHash = $modelClass::chainHash($row->prev_hash, $model->hashPayload());

            if (! hash_equals($expectedHash, (string) $row->hash)) {
                $this->error("[{$table}] Integritätsverletzung bei id={$row->id}: Zeilen-Hash stimmt nicht (Eintrag wurde nachträglich verändert).");
                $this->line("  Geprüft bis zum Bruch: {$checked} Einträge.");

                return self::FAILURE;
            }

            $expectedPrev = $row->hash;
            $checked++;
        }

        $this->info("[{$table}] OK – Hash-Kette intakt ({$checked} Einträge geprüft).");

        return self::SUCCESS;
    }
}
