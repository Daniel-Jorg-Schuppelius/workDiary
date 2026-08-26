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
use App\Enums\Notification\NotificationEvent;
use App\Models\{ComplianceFinding, Organization, User};
use App\Services\Notification\NotificationDispatcher;
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
     * @param  string  $category  Befund-Kategorie (arbzg | plausibility); Dedup und Auto-„behoben" bleiben je Kategorie getrennt
     * @return array{created:int, updated:int, reopened:int, resolved:int}
     */
    public function record(Organization $organization, CarbonImmutable $from, CarbonImmutable $to, array $findingsByUser, string $category = self::CATEGORY): array {
        $orgId = (int) $organization->getKey();
        $scanAt = Carbon::now();
        $stats = ['created' => 0, 'updated' => 0, 'reopened' => 0, 'resolved' => 0];

        DB::transaction(function () use ($orgId, $from, $findingsByUser, $scanAt, $category, &$stats): void {
            /** @var list<int> $touchedIds */
            $touchedIds = [];

            foreach ($findingsByUser as $userId => $findings) {
                foreach ($findings as $finding) {
                    $key = $this->dedupKey($userId, $finding, $category);

                    /** @var ComplianceFinding|null $model */
                    $model = ComplianceFinding::query()
                        ->where('organization_id', $orgId)
                        ->where('dedup_key', $key)
                        ->first();

                    if ($model === null) {
                        $model = new ComplianceFinding;
                        $model->organization_id = $orgId;
                        $model->category = $category;
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
                        $this->notifyAffected($model, $category);
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
            // über die berührten IDs. Ältere ausserhalb des Fensters (scope_date < from) bleiben unberührt;
            // fremde Kategorien ebenfalls (arbzg- und plausibility-Läufe schließen sich nicht gegenseitig).
            ComplianceFinding::query()
                ->where('organization_id', $orgId)
                ->where('category', $category)
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
     * MVP-538 (Q1 S. 48): neuer „Ungeklärter Fall" an die betroffene Person —
     * nur Plausibilitäts-Kategorie, einmal je Befund (Dispatcher-Dedup); die
     * Org-Regel zum Event kann den Versand abschalten.
     */
    private function notifyAffected(ComplianceFinding $model, string $category): void {
        if ($model->subject_type !== User::class) {
            return;
        }
        if ($category === DrivingTimeComplianceChecker::CATEGORY) {
            $this->notifyDrivingTime($model);

            return;
        }
        if ($category !== AttendancePlausibilityScanService::CATEGORY) {
            return;
        }
        $user = User::query()->find($model->subject_id);
        if ($user === null) {
            return;
        }
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::AttendanceUnclearCase,
            $model,
            $user,
            [
                'title' => (string) __('notification.message.unclear_case_title', [
                    'date' => $model->scope_date->format('d.m.Y'),
                ]),
                'title_key' => 'notification.message.unclear_case_title',
                'title_params' => ['date' => $model->scope_date->toDateString()],
                'message' => (string) __('compliance.report.kind.' . $model->rule_code),
                'message_key' => 'compliance.report.kind.' . $model->rule_code,
                'url' => route('overtime.index'),
            ],
            dedup: true,
        );
    }

    /**
     * Feature 144 (MVP-719): neuer Lenk-/Ruhezeit-Befund an den Fahrer
     * (notify_affected) und die Disposition (Default-Rolle Teamleitung) —
     * render-time über title_key/message_key, einmal je Befund.
     */
    private function notifyDrivingTime(ComplianceFinding $model): void {
        $driver = User::query()->find($model->subject_id);
        if ($driver === null) {
            return;
        }
        $kindKey = 'compliance.report.kind.' . $model->rule_code;
        app(NotificationDispatcher::class)->notify(
            NotificationEvent::DrivingTimeViolation,
            $model,
            $driver,
            [
                'title' => (string) __('notification.message.driving_time_violation_title', [
                    'date' => $model->scope_date->format('d.m.Y'),
                    'driver' => $driver->name,
                ]),
                'title_key' => 'notification.message.driving_time_violation_title',
                'title_params' => ['date' => $model->scope_date->toDateString(), 'driver' => $driver->name],
                'message' => (string) __($kindKey),
                'message_key' => $kindKey,
                'url' => route('reports.arbzg-compliance', ['category' => DrivingTimeComplianceChecker::CATEGORY]),
            ],
            dedup: true,
        );
    }

    /**
     * Stabiler Identitäts-Schlüssel für die Dedup: Kategorie + Regel +
     * Subjekt-Morph + Zeitbezug. Subjekt ist heute stets ein User; der
     * Klassen-Basisname hält den Schlüssel kurz (< 191 Zeichen).
     */
    private function dedupKey(int $userId, AttendanceComplianceFinding $finding, string $category): string {
        return sprintf('%s|%s|User#%d|%s', $category, $finding->kind, $userId, $finding->date);
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
