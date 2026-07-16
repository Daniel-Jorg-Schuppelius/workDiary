<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ComplianceFindingRecorder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\Compliance\ComplianceFindingStatus;
use App\Models\{ComplianceFinding, Organization, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persistiert die on-the-fly ermittelten ArbZG-Verstöße
 * ({@see ComplianceScanService}) revisionssicher in `compliance_findings`
 * (Feature 006, Welle D).
 *
 * Upsert über den stabilen `dedup_key` (Kategorie + Regel + Subjekt-Morph +
 * Zeitbezug): ein neuer Verstoß wird angelegt (Status open), ein weiterhin
 * bestehender aktualisiert, ein zuvor als `resolved` markierter beim
 * Wiederauftreten reaktiviert. Verstöße im Scan-Fenster, die NICHT mehr
 * auftreten, werden auf `resolved` gesetzt — NICHT gelöscht (append-only-Geist).
 * Jeder Statuswechsel wird explizit in die Audit-Hash-Kette geschrieben.
 */
final class ComplianceFindingRecorder {
    public const CATEGORY = 'arbzg';

    /**
     * @param  array<int, list<AttendanceComplianceFinding>>  $findingsByUser  Befunde je user_id (aus {@see ComplianceScanService})
     * @return array{created:int, updated:int, reopened:int, resolved:int}
     */
    public function record(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, array $findingsByUser): array {
        $orgId = (int) $organization->getKey();
        $scanAt = Carbon::now();
        $stats = ['created' => 0, 'updated' => 0, 'reopened' => 0, 'resolved' => 0];

        DB::transaction(function () use ($orgId, $from, $findingsByUser, $scanAt, &$stats): void {
            /** @var list<int> $touchedIds */
            $touchedIds = [];

            foreach ($findingsByUser as $userId => $findings) {
                foreach ($findings as $finding) {
                    $key = $this->dedupKey($userId, $finding);

                    /** @var ComplianceFinding|null $model */
                    $model = ComplianceFinding::query()
                        ->where('organization_id', $orgId)
                        ->where('dedup_key', $key)
                        ->first();

                    if ($model === null) {
                        $model = new ComplianceFinding;
                        $model->organization_id = $orgId;
                        $model->category = self::CATEGORY;
                        $model->rule_code = $finding->kind;
                        $model->severity = $finding->severity;
                        $model->subject_type = User::class;
                        $model->subject_id = (int) $userId;
                        $model->scope_date = Carbon::parse($finding->date);
                        $model->detected_value = $finding->value;
                        $model->threshold_value = $finding->threshold;
                        $model->dedup_key = $key;
                        $model->status = ComplianceFindingStatus::Open;
                        $model->first_detected_at = $scanAt;
                        $model->last_detected_at = $scanAt;
                        $model->save();
                        $model->audit('compliance.finding.detected', $this->context($finding));
                        $stats['created']++;
                    } else {
                        $model->severity = $finding->severity;
                        $model->detected_value = $finding->value;
                        $model->threshold_value = $finding->threshold;
                        $model->last_detected_at = $scanAt;

                        $reopened = false;
                        if ($model->status === ComplianceFindingStatus::Resolved) {
                            $model->status = ComplianceFindingStatus::Open;
                            $model->resolved_at = null;
                            $reopened = true;
                        }
                        $model->save();
                        if ($reopened) {
                            $model->audit('compliance.finding.reopened', $this->context($finding));
                            $stats['reopened']++;
                        }
                        $stats['updated']++;
                    }

                    $touchedIds[] = (int) $model->getKey();
                }
            }

            // Auto-„behoben": Befunde im Scan-Fenster, die dieser Lauf nicht erneut erkannte, abgegrenzt
            // über die berührten IDs. Ältere ausserhalb des Fensters (scope_date < from) bleiben unberührt.
            ComplianceFinding::query()
                ->where('organization_id', $orgId)
                ->whereIn('status', [
                    ComplianceFindingStatus::Open->value,
                    ComplianceFindingStatus::Acknowledged->value,
                    ComplianceFindingStatus::Accepted->value,
                ])
                ->where('scope_date', '>=', $from->toDateString())
                ->whereNotIn('id', $touchedIds)
                ->get()
                ->each(function (ComplianceFinding $model) use ($scanAt, &$stats): void {
                    $model->status = ComplianceFindingStatus::Resolved;
                    $model->resolved_at = $scanAt;
                    $model->save();
                    $model->audit('compliance.finding.resolved', [
                        'rule' => $model->rule_code,
                        'scope_date' => $model->scope_date->toDateString(),
                        'auto' => true,
                    ]);
                    $stats['resolved']++;
                });
        });

        return $stats;
    }

    /**
     * Stabiler Identitäts-Schlüssel für die Dedup: Kategorie + Regel +
     * Subjekt-Morph + Zeitbezug. Subjekt ist heute stets ein User; der
     * Klassen-Basisname hält den Schlüssel kurz (< 191 Zeichen).
     */
    private function dedupKey(int $userId, AttendanceComplianceFinding $finding): string {
        return sprintf('%s|%s|User#%d|%s', self::CATEGORY, $finding->kind, $userId, $finding->date);
    }

    /** @return array{rule:string, severity:string, scope_date:string} */
    private function context(AttendanceComplianceFinding $finding): array {
        return [
            'rule' => $finding->kind,
            'severity' => $finding->severity,
            'scope_date' => $finding->date,
        ];
    }
}
