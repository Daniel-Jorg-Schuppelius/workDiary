<?php
/*
 * Created on   : Sun Aug 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditRedactionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\{AuditLog, AuditRedaction, User};
use App\Models\Concerns\HashChainable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Schwärzt einzelne Werte im Audit-Protokoll auf ein Löschverlangen nach
 * Art. 17 DSGVO hin (Sicherheitsscan 2026-08-23, S-21).
 *
 * **Warum das überhaupt nötig ist:** `audit_logs.changes` geht in den
 * Zeilen-Hash ein und die Zeilen sind vorwärts verkettet. Ein Wert, der einmal
 * protokolliert wurde, ist damit weder löschbar noch überschreibbar, ohne die
 * Kette zu brechen — auch dann nicht, wenn ein Betroffener die Löschung
 * verlangt und kein Aufbewahrungsgrund entgegensteht.
 *
 * **Was dieser Dienst tut:** er ersetzt die betroffenen Werte durch den
 * Platzhalter, rechnet die Kette ab der ersten geänderten Zeile neu und
 * schreibt den Kettenkopf fort. `audit:verify` bleibt danach grün.
 *
 * **Warum das trotzdem nachweisbar bleibt:** jede Schwärzung wird in
 * `audit_redactions` festgehalten — mit Kettenkopf davor und danach, Umfang,
 * Anlass und handelnder Person. Diese Tabelle ist selbst hash-verkettet und
 * append-only. Ein „grünes" audit:verify ohne passenden Schwärzungseintrag ist
 * also weiterhin der Beleg dafür, dass nichts angefasst wurde; ein Eingriff
 * hinterlässt eine Spur in einer zweiten Kette statt gar keine.
 *
 * Der Dienst schreibt bewusst über den Query Builder: das Modell ist
 * append-only ({@see \App\Models\Concerns\AppendOnly}) und würde jedes UPDATE
 * abweisen. Das ist die **einzige** Stelle, die daran vorbeischreiben darf.
 */
class AuditRedactionService {
    public function __construct(private readonly AuditChainVerifier $verifier) {}

    /**
     * @param  list<string>  $fields  Zu schwärzende Feldnamen
     * @return array{rows: int, chains: list<string>, redactions: list<int>}
     */
    public function redact(
        string $chainTable,
        string $auditableType,
        int $auditableId,
        array $fields,
        string $reason,
        ?string $requestReference = null,
        ?User $actor = null,
        bool $dryRun = false,
    ): array {
        if ($fields === []) {
            throw new InvalidArgumentException('Ohne Feldliste gibt es nichts zu schwärzen.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Eine Schwärzung ohne Begründung ist nicht nachweisbar.');
        }

        $chains = $this->verifier->chains();
        if (! isset($chains[$chainTable])) {
            throw new InvalidArgumentException("Unbekannte Kette: {$chainTable}.");
        }

        /** @var class-string<HashChainable> $modelClass */
        $modelClass = $chains[$chainTable];

        $affected = DB::table($chainTable)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->orderBy('id')
            ->get();

        if ($affected->isEmpty()) {
            return ['rows' => 0, 'chains' => [], 'redactions' => []];
        }

        $touched = [];
        foreach ($affected as $row) {
            $changes = json_decode((string) $row->changes, true);
            if (! is_array($changes)) {
                continue;
            }

            $redacted = $this->redactPayload($changes, $fields);
            if ($redacted === $changes) {
                continue;
            }

            $touched[(int) $row->organization_id][] = [
                'id' => (int) $row->id,
                'changes' => $redacted,
            ];
        }

        if ($touched === []) {
            return ['rows' => 0, 'chains' => [], 'redactions' => []];
        }

        if ($dryRun) {
            return [
                'rows' => array_sum(array_map('count', $touched)),
                'chains' => array_map(static fn(int $org): string => "{$chainTable}:{$org}", array_keys($touched)),
                'redactions' => [],
            ];
        }

        $rows = 0;
        $chainNames = [];
        $redactionIds = [];

        DB::transaction(function () use ($chainTable, $modelClass, $touched, $fields, $reason, $requestReference, $actor, $auditableType, $auditableId, &$rows, &$chainNames, &$redactionIds): void {
            foreach ($touched as $organizationId => $entries) {
                $chainName = "{$chainTable}:{$organizationId}";
                $chainNames[] = $chainName;

                $headBefore = DB::table('audit_chain_heads')->where('chain', $chainName)->value('head_hash');

                foreach ($entries as $entry) {
                    DB::table($chainTable)
                        ->where('id', $entry['id'])
                        ->update(['changes' => json_encode($entry['changes'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    $rows++;
                }

                $ids = array_column($entries, 'id');
                $headAfter = $this->rebuildChain($chainTable, $modelClass, $chainName, (int) $organizationId, min($ids));

                $redaction = new AuditRedaction([
                    'organization_id' => $organizationId > 0 ? $organizationId : null,
                    'chain' => $chainName,
                    'auditable_type' => $auditableType,
                    'auditable_id' => $auditableId,
                    'fields' => $fields,
                    'rows_affected' => count($entries),
                    'first_audit_log_id' => min($ids),
                    'last_audit_log_id' => max($ids),
                    'reason' => $reason,
                    'request_reference' => $requestReference,
                    'performed_by' => $actor?->getKey(),
                    'head_before' => $headBefore,
                    'head_after' => $headAfter,
                ]);
                $redaction->save();

                $redactionIds[] = (int) $redaction->getKey();
            }
        });

        return ['rows' => $rows, 'chains' => $chainNames, 'redactions' => $redactionIds];
    }

    /**
     * Ersetzt Werte der genannten Felder — sowohl im flachen Format
     * (created/deleted) als auch im before/after-Format (updated).
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload, array $fields): array {
        $isNested = isset($payload['before']) || isset($payload['after']);

        if ($isNested) {
            foreach (['before', 'after'] as $section) {
                if (is_array($payload[$section] ?? null)) {
                    $payload[$section] = $this->maskFields($payload[$section], $fields);
                }
            }

            return $payload;
        }

        return $this->maskFields($payload, $fields);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function maskFields(array $values, array $fields): array {
        foreach ($fields as $field) {
            if (array_key_exists($field, $values) && $values[$field] !== null) {
                $values[$field] = AuditLog::REDACTED;
            }
        }

        return $values;
    }

    /**
     * Rechnet die Kette ab `$fromId` neu und schreibt den Kettenkopf fort.
     *
     * Der prev_hash der ersten neu gerechneten Zeile bleibt unverändert — sie
     * hängt weiter an ihrer Vorgängerin. Nur ab ihr wandert alles nach.
     *
     * @param  class-string<HashChainable>  $modelClass
     */
    private function rebuildChain(string $chainTable, string $modelClass, string $chainName, int $organizationId, int $fromId): ?string {
        $query = DB::table($chainTable)->orderBy('id');
        $organizationId > 0
            ? $query->where('organization_id', $organizationId)
            : $query->whereNull('organization_id');

        $rows = (clone $query)->where('id', '>=', $fromId)->get();
        if ($rows->isEmpty()) {
            throw new RuntimeException("Kette {$chainName}: keine Zeilen ab id={$fromId}.");
        }

        $prev = (string) $rows->first()->prev_hash !== ''
            ? (string) $rows->first()->prev_hash
            : null;

        $head = null;
        foreach ($rows as $row) {
            $model = $modelClass::fromStorageRow((array) $row);
            $hash = $modelClass::chainHash($prev, $model->hashPayload());

            DB::table($chainTable)->where('id', $row->id)->update([
                'prev_hash' => $prev,
                'hash' => $hash,
            ]);

            $prev = $hash;
            $head = $hash;
        }

        DB::table('audit_chain_heads')->where('chain', $chainName)->update(['head_hash' => $head]);

        return $head;
    }
}
