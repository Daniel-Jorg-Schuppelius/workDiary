<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_08_100000_rechain_audit_hashes_after_value_object_casts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Models\{AuditLog, CashEntry};
use App\Models\Concerns\HashChainable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Log, Schema};

/**
 * Repariert die Hash-Ketten nach der ValueObject-Cast-Regression (1e6320f0):
 * hashPayload() serialisierte `ip` (AuditLog) als {} bzw. `amount` (CashEntry)
 * als "12.34 EUR" — Zeilen aus diesem Zeitraum rechnen unter der korrigierten
 * Semantik anders und die gesamte Folge-Kette muss neu verkettet werden.
 *
 * Echtheitsnachweis vor jedem Rewrite: Der gespeicherte Hash MUSS entweder der
 * korrekten oder exakt der Cast-defekten Semantik über dem gespeicherten
 * prev_hash entsprechen — sonst bricht die Migration ab (echte Veränderung,
 * es wird nichts überschrieben). Jeder Rewrite wird mit Alt-/Neu-Hash als
 * JSONL protokolliert (storage/app/audit-chain-repair-*.jsonl, GoBD-Nachweis).
 */
return new class extends Migration {
    public function up(): void {
        $proofPath = storage_path('app/audit-chain-repair-' . now()->format('Ymd_His') . '.jsonl');

        // audit_logs: zwei anerkannte Artefakt-Klassen — (1) Cast-Regression
        // hashte `ip` als leeres Objekt ({}), (2) ON DELETE SET NULL nullte
        // user_id/organization_id beim Löschen des Users/der Org nachträglich
        // (Originalwert steckt meist im changes-JSON, sonst kleiner Suchraum).
        $this->rechain('audit_logs', AuditLog::class, $proofPath, static function (array $payload): array {
            $changes = is_array($payload['changes']) ? $payload['changes'] : [];

            $ips = ['raw' => $payload['ip']];
            if ($payload['ip'] !== null) {
                $ips['object'] = new stdClass;
                $ips['null'] = null;
            }

            $fkCandidates = static function (?int $stored, string $key) use ($changes): array {
                $candidates = ['stored' => $stored];
                if ($stored === null) {
                    if (isset($changes[$key])) {
                        $candidates['fk_' . (int) $changes[$key]] = (int) $changes[$key];
                    }
                    for ($i = 1; $i <= 10; $i++) {
                        $candidates['fk_' . $i] = $i;
                    }
                }

                return $candidates;
            };

            $variants = [];
            foreach ($fkCandidates($payload['user_id'], 'user_id') as $uLabel => $userId) {
                foreach ($fkCandidates($payload['organization_id'], 'organization_id') as $oLabel => $orgId) {
                    foreach ($ips as $iLabel => $ip) {
                        if ($uLabel === 'stored' && $oLabel === 'stored' && $iLabel === 'raw') {
                            continue; // korrekte Semantik — prüft der Aufrufer bereits
                        }
                        $variants["user={$uLabel}|org={$oLabel}|ip={$iLabel}"] = array_replace($payload, [
                            'user_id' => $userId,
                            'organization_id' => $orgId,
                            'ip' => $ip,
                        ]);
                    }
                }
            }

            return $variants;
        });

        // cash_entries: defekte Variante hashte `amount` als "(string) Money" ("12.34 EUR").
        $this->rechain('cash_entries', CashEntry::class, $proofPath, static function (array $payload, HashChainable $model): array {
            /** @var CashEntry $model */
            $castAmount = (string) $model->getAttribute('amount');

            return $castAmount === $payload['amount']
                ? []
                : ['amount_money' => array_replace($payload, ['amount' => $castAmount])];
        });
    }

    /**
     * Läuft die Kette in id-Reihenfolge ab und verkettet ab der ersten
     * Abweichung mit korrekter Semantik neu. $brokenVariants liefert je Zeile
     * die als Software-Artefakt anerkannten Alt-Payloads (leer = keine).
     *
     * @param class-string<HashChainable> $modelClass
     * @param Closure(array<string, mixed>, HashChainable): array<string, array<string, mixed>> $brokenVariants
     */
    private function rechain(string $table, string $modelClass, string $proofPath, Closure $brokenVariants): void {
        if (! Schema::hasTable($table) || DB::table($table)->count() === 0) {
            return;
        }

        /** @var list<string> $proof gepufferte Nachweiszeilen (siehe unten) */
        $proof = [];

        DB::transaction(function () use ($table, $modelClass, $proofPath, $brokenVariants, &$proof): void {
            // Kettenkopf sperren — serialisiert gegen parallele Live-Inserts (wie HashChained::performInsert).
            DB::table('audit_chain_heads')->insertOrIgnore(['chain' => $table, 'head_hash' => null, 'height' => 0]);
            DB::table('audit_chain_heads')->where('chain', $table)->lockForUpdate()->first();

            $storedPrev = null;   // Kettenstand, wie er in der DB liegt
            $runningPrev = null;  // Kettenstand nach Korrektur
            $rewritten = 0;
            $rows = 0;

            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                $rows++;
                if ($row->prev_hash !== $storedPrev) {
                    throw new RuntimeException("[{$table}] prev_hash-Verkettung gebrochen bei id={$row->id} — keine Reparatur, audit:verify-Befund klären.");
                }

                $model = $modelClass::fromStorageRow((array) $row);
                $payload = $model->hashPayload();
                $correctOverStored = $modelClass::chainHash($storedPrev, $payload);

                $variant = null;
                if (! hash_equals($correctOverStored, (string) $row->hash)) {
                    foreach ($brokenVariants($payload, $model) as $name => $brokenPayload) {
                        if (hash_equals($this->hashWithoutGuard($storedPrev, $brokenPayload), (string) $row->hash)) {
                            $variant = $name;
                            break;
                        }
                    }
                    if ($variant === null) {
                        throw new RuntimeException("[{$table}] id={$row->id}: Hash entspricht weder korrekter noch Cast-defekter Semantik — mögliche echte Veränderung, Abbruch ohne Rewrite.");
                    }
                }

                $newHash = $modelClass::chainHash($runningPrev, $payload);
                if (! hash_equals($newHash, (string) $row->hash) || $row->prev_hash !== $runningPrev) {
                    DB::table($table)->where('id', $row->id)->update(['prev_hash' => $runningPrev, 'hash' => $newHash]);
                    $proof[] = json_encode([
                        'table' => $table,
                        'id' => $row->id,
                        'variant' => $variant ?? 'rechained_after_divergence',
                        'old_prev_hash' => $row->prev_hash,
                        'old_hash' => $row->hash,
                        'new_prev_hash' => $runningPrev,
                        'new_hash' => $newHash,
                    ], JSON_UNESCAPED_SLASHES) . "\n";
                    $rewritten++;
                }

                $storedPrev = (string) $row->hash;
                $runningPrev = $newHash;
            }

            if ($rewritten > 0) {
                DB::table('audit_chain_heads')->where('chain', $table)->update(['head_hash' => $runningPrev, 'height' => $rows]);
                Log::notice('audit.chain_repaired', ['table' => $table, 'rows' => $rows, 'rewritten' => count($proof), 'proof' => $proofPath]);
            }
        });

        // Nachweisdatei erst NACH dem Commit und nur bei echter Umkettung
        // (MVP-723): vorher entstand bei jedem Migrationslauf eine Datei,
        // sobald auch nur eine Zeile angefasst wurde — und bei einem Abbruch
        // (RuntimeException → Rollback) stand ein Nachweis über Rewrites da,
        // die es nie gab. Kein Rewrite ⇒ keine Datei.
        if ($proof !== []) {
            file_put_contents($proofPath, implode('', $proof), FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * chainHash ohne den Objekt-Guard — nur zur Rekonstruktion der defekten
     * Alt-Semantik (ip als {}), die der Guard heute zu Recht ablehnt. Repliziert
     * die Decode-Normalisierung von HashChained::canonicalPayload().
     *
     * @param array<string, mixed> $data
     */
    private function hashWithoutGuard(?string $prevHash, array $data): string {
        $normalized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = $decoded;
                }
            }
            $normalized[$key] = $value;
        }

        return hash('sha256', (string) $prevHash . '|' . json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function down(): void {
        // Rewrite alter (defekter) Hashes ist nicht umkehrbar — Protokoll liegt als JSONL in storage/app.
    }
};
