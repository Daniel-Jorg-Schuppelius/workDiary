<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditChainVerifier.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\Concerns\HashChainable;
use Illuminate\Support\Facades\DB;

/**
 * Rechnet die SHA-256-Hash-Ketten (config/audit.php `chains`) in id-Reihenfolge
 * nach — Schreibpfad-identisch über {@see HashChainable::hashPayload()} und
 * {@see HashChainable::chainName()}, damit treiberunabhängig. Aus
 * `audit:verify` herausgelöst (MVP-699), weil die Verfahrensdokumentation
 * dasselbe Ergebnis als Nachweis einfriert.
 *
 * Eine Tabelle trägt seit MVP-722 mehrere Ketten (eine je Organisation); der
 * Durchlauf führt deshalb einen Erwartungswert JE KETTE mit.
 */
final class AuditChainVerifier {
    public const REASON_PREV_HASH = 'prev_hash';

    public const REASON_ROW_HASH = 'row_hash';

    /** Kettenkopf passt nicht zur letzten Zeile — Zeilen am Ende entfernt. */
    public const REASON_HEAD_HASH = 'head_hash';

    /** Zeilenzahl passt nicht zur festgehaltenen Kettenhöhe. */
    public const REASON_HEIGHT = 'height';

    /** @return array<string, class-string<HashChainable>> Tabelle → Modell */
    public function chains(): array {
        $result = [];
        foreach ((array) config('audit.chains', []) as $table => $class) {
            if (is_string($table) && is_string($class) && is_a($class, HashChainable::class, true)) {
                $result[$table] = $class;
            }
        }

        return $result;
    }

    /**
     * Prüft alle Ketten einer Tabelle in einem Durchlauf. Seit MVP-722 führt
     * jede Organisation ihre eigene Kette ({@see HashChainable::chainName()}),
     * die Vorgängerzeile ist also die vorige Zeile DERSELBEN Kette — ein
     * gemeinsamer id-Durchlauf reicht trotzdem, weil die Ketten sich nur
     * verschränken, nicht überlappen. Bricht beim ersten Fehler ab.
     *
     * @param  class-string<HashChainable>  $modelClass
     * @return array{table: string, ok: bool, checked: int, chains: int, failed_id: int|null, failed_chain: string|null, reason: string|null}
     */
    public function verify(string $table, string $modelClass): array {
        /** @var array<string, string|null> $expectedPrev Kette → zuletzt gesehener Hash */
        $expectedPrev = [];
        /** @var array<string, int> $heights Kette → gezählte Zeilen */
        $heights = [];
        $checked = 0;

        foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
            $model = $modelClass::fromStorageRow((array) $row);
            $chain = $model->chainName();

            if ($row->prev_hash !== ($expectedPrev[$chain] ?? null)) {
                return ['table' => $table, 'ok' => false, 'checked' => $checked, 'chains' => count($expectedPrev),
                    'failed_id' => (int) $row->id, 'failed_chain' => $chain, 'reason' => self::REASON_PREV_HASH];
            }

            $expectedHash = $modelClass::chainHash($row->prev_hash, $model->hashPayload());

            if (! hash_equals($expectedHash, (string) $row->hash)) {
                return ['table' => $table, 'ok' => false, 'checked' => $checked, 'chains' => count($expectedPrev),
                    'failed_id' => (int) $row->id, 'failed_chain' => $chain, 'reason' => self::REASON_ROW_HASH];
            }

            $expectedPrev[$chain] = $row->hash;
            $heights[$chain] = ($heights[$chain] ?? 0) + 1;
            $checked++;
        }

        return $this->verifyHeads($table, $expectedPrev, $heights, $checked);
    }

    /**
     * Gleicht die Kettenköpfe gegen das Ergebnis des Durchlaufs ab.
     *
     * Ohne diesen Schritt bleibt das **Abschneiden am Ende** unentdeckt
     * (Sicherheitsscan 2026-08-23, S-36): wer die letzten Zeilen einer Kette
     * löscht und `head_hash`/`height` mitzieht, hinterlässt eine Kette, die
     * sich zeilenweise fehlerfrei nachrechnen lässt — sie ist nur kürzer.
     * Der Kopf ist der einzige Anker, der sagt, wie weit sie reichen müsste;
     * `audit:db-hardening-sql` lässt `audit_chain_heads` bewusst beschreibbar,
     * die Prüfung ist also kein Ersatz für eine externe Verankerung, aber die
     * Hürde steigt von „eine Zeile löschen" auf „zwei Tabellen konsistent
     * fälschen".
     *
     * @param  array<string, string|null>  $expectedPrev
     * @param  array<string, int>  $heights
     * @return array{table: string, ok: bool, checked: int, chains: int, failed_id: int|null, failed_chain: string|null, reason: string|null}
     */
    private function verifyHeads(string $table, array $expectedPrev, array $heights, int $checked): array {
        // Ketten dieser Tabelle: `tabelle` oder `tabelle:organisation`.
        $heads = [];
        foreach (DB::table('audit_chain_heads')->get() as $head) {
            $chain = (string) $head->chain;
            if ($chain === $table || str_starts_with($chain, $table . ':')) {
                $heads[$chain] = $head;
            }
        }

        // Eine Kette ohne Kopf ist genauso verdächtig wie ein Kopf ohne
        // Zeilen — beide Richtungen prüfen.
        $chainNames = array_unique(array_merge(array_keys($expectedPrev), array_keys($heads)));
        $count = count($chainNames);

        foreach ($chainNames as $chain) {
            $lastHash = $expectedPrev[$chain] ?? null;

            if (! isset($heads[$chain])) {
                // Der Kopf wird beim Einfügen mitgeschrieben; sein Fehlen
                // heißt, dass er entfernt wurde.
                return ['table' => $table, 'ok' => false, 'checked' => $checked, 'chains' => $count,
                    'failed_id' => null, 'failed_chain' => $chain, 'reason' => self::REASON_HEAD_HASH];
            }

            if (! hash_equals((string) $lastHash, (string) $heads[$chain]->head_hash)) {
                return ['table' => $table, 'ok' => false, 'checked' => $checked, 'chains' => $count,
                    'failed_id' => null, 'failed_chain' => $chain, 'reason' => self::REASON_HEAD_HASH];
            }

            if ((int) $heads[$chain]->height !== ($heights[$chain] ?? 0)) {
                return ['table' => $table, 'ok' => false, 'checked' => $checked, 'chains' => $count,
                    'failed_id' => null, 'failed_chain' => $chain, 'reason' => self::REASON_HEIGHT];
            }
        }

        return ['table' => $table, 'ok' => true, 'checked' => $checked, 'chains' => $count,
            'failed_id' => null, 'failed_chain' => null, 'reason' => null];
    }
}
