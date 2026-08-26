<?php
/*
 * Created on   : Sat Jun 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VerifyAuditLogChain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Services\Audit\AuditChainVerifier;
use Illuminate\Console\Command;

/**
 * Prüft die Unveränderbarkeit der Audit-Ketten (GoBD), indem die SHA-256-Hash-
 * Ketten einer Tabelle — seit MVP-722 eine je Organisation — in id-Reihenfolge
 * nachgerechnet werden. Gemeldet werden:
 *   - ein nicht passender Zeilen-Hash  → Zeile wurde nachträglich verändert
 *   - ein gebrochener prev_hash-Verweis → Zeile gelöscht/eingeschoben
 * Exit-Code 1, sobald eine Kette an einer Stelle bricht (CI-/Cron-tauglich).
 *
 * Die Rechnung selbst liegt im {@see AuditChainVerifier} (MVP-699: die
 * Verfahrensdokumentation friert dasselbe Ergebnis als Nachweis ein).
 */
class VerifyAuditLogChain extends Command {
    protected $signature = 'audit:verify {--chain= : Nur diese Kette prüfen (audit_logs|organization_audit_logs)}';

    protected $description = 'Prüft die Integrität der Audit-Hash-Ketten (GoBD-Unveränderbarkeit).';

    public function handle(AuditChainVerifier $verifier): int {
        $chains = $verifier->chains();

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
            $result = $verifier->verify($table, $modelClass);
            if ($result['ok']) {
                $this->info("[{$table}] OK – {$result['chains']} Kette(n) intakt ({$result['checked']} Einträge geprüft).");

                continue;
            }

            $this->error($result['reason'] === AuditChainVerifier::REASON_PREV_HASH
                ? "[{$result['failed_chain']}] Integritätsverletzung bei id={$result['failed_id']}: prev_hash verweist nicht auf die Vorgängerzeile derselben Kette (Eintrag gelöscht oder eingeschoben)."
                : "[{$result['failed_chain']}] Integritätsverletzung bei id={$result['failed_id']}: Zeilen-Hash stimmt nicht (Eintrag wurde nachträglich verändert).");
            $this->line("  Geprüft bis zum Bruch: {$result['checked']} Einträge.");
            $exit = self::FAILURE;
        }

        return $exit;
    }
}
