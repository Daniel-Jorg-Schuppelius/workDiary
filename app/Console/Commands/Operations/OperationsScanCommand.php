<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsScanCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Enums\Support\ProblemReportStatus;
use App\Models\{Organization, ProblemReport, SupportAccessGrant};
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Console\Command;

/**
 * Betriebsaufgaben-Scanner (Feature 041, MVP-058): synchronisiert
 * Aufgaben mit ihren Ursachen — erzeugt sie idempotent und löst sie
 * automatisch auf, wenn die Ursache weggefallen ist (keine
 * Geisteraufgaben). Weitere Teilscans (Backup MVP-056, Ablauf MVP-057)
 * docken hier an.
 */
class OperationsScanCommand extends Command {
    protected $signature = 'operations:scan';

    protected $description = 'Synchronisiert Betriebsaufgaben (Supportfreigaben, Fehlermeldungen) mit ihren Ursachen';

    public function handle(OperationsAlertService $alerts): int {
        // Wartungsfenster-Lebenszyklus (MVP-055): ankündigen/starten/beenden.
        app(\App\Services\Operations\MaintenanceWindowService::class)->tick();

        $this->scanBackupHeartbeat($alerts);
        $this->scanRestoreTests($alerts);

        // Ablauf-/Verbindungswarnungen (MVP-057) inkl. Auto-Resolve.
        app(\App\Services\Operations\Expiry\ExpiryScanner::class)->scan($alerts);

        foreach (Organization::query()->orderBy('id')->pluck('id') as $organizationId) {
            $this->scanSupportGrants($alerts, (int) $organizationId);
            $this->scanProblemReports($alerts, (int) $organizationId);
        }

        $this->info('Betriebsaufgaben synchronisiert.');

        return self::SUCCESS;
    }

    /**
     * Backup-Heartbeat-Überfälligkeit (Feature 017, MVP-056): 26 h →
     * Warnung, 72 h → kritische Hochstufung derselben Aufgabe (der
     * AlertService hebt Severity an und meldet erneut). Installationen
     * ohne jeglichen Heartbeat meldet bewusst NICHT dieser Scan —
     * das deckt die Onboarding-Checkliste (backup.heartbeat) ab.
     */
    private function scanBackupHeartbeat(OperationsAlertService $alerts): void {
        $last = \App\Models\BackupHeartbeat::query()->orderByDesc('occurred_at')->first();
        if ($last === null) {
            return;
        }

        $warnHours = (int) \App\Support\Setting::get('backup.thresholds_hours.warn', 26);
        $criticalHours = (int) \App\Support\Setting::get('backup.thresholds_hours.critical', 72);
        $ageHours = (int) $last->occurred_at->diffInHours(now(), true);

        if ($ageHours <= $warnHours) {
            $alerts->resolve('backup_overdue');

            return;
        }

        $alerts->report(new OperationsSignal(
            type: OperationsTaskType::BackupOverdue,
            dedupeKey: 'backup_overdue',
            severity: $ageHours > $criticalHours
                ? OperationsTaskSeverity::Critical
                : OperationsTaskSeverity::Warning,
            titleKey: 'operations.task.backup_overdue',
            params: ['hours' => $ageHours, 'threshold' => $ageHours > $criticalHours ? $criticalHours : $warnHours],
            linkRoute: 'admin.backup.status',
        ));
    }

    /** Restore-Test überfällig oder nie protokolliert (MVP-056). */
    private function scanRestoreTests(OperationsAlertService $alerts): void {
        // Ohne Backup-Betrieb (kein Heartbeat) wäre eine Restore-Test-
        // Aufgabe reiner Lärm auf Frischinstallationen.
        if (!\App\Models\BackupHeartbeat::query()->exists()) {
            return;
        }

        $thresholdDays = (int) \App\Support\Setting::get('backup.restore_test_overdue_days', 180);
        $lastTest = \App\Models\RestoreTest::query()->orderByDesc('tested_on')->first();

        if ($lastTest === null) {
            $alerts->report(new OperationsSignal(
                type: OperationsTaskType::RestoreTestOverdue,
                dedupeKey: 'restore_test_overdue',
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.restore_test_missing',
                linkRoute: 'admin.backup.status',
            ));

            return;
        }

        $ageDays = (int) $lastTest->tested_on->diffInDays(now(), true);
        if ($ageDays > $thresholdDays) {
            $alerts->report(new OperationsSignal(
                type: OperationsTaskType::RestoreTestOverdue,
                dedupeKey: 'restore_test_overdue',
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.restore_test_overdue',
                params: ['days' => $ageDays, 'threshold' => $thresholdDays],
                linkRoute: 'admin.backup.status',
            ));
        } else {
            $alerts->resolve('restore_test_overdue');
        }
    }

    private function scanSupportGrants(OperationsAlertService $alerts, int $organizationId): void {
        $active = SupportAccessGrant::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();

        $dedupeKey = "support_grant_open:org:{$organizationId}";
        if ($active > 0) {
            $alerts->report(new OperationsSignal(
                type: OperationsTaskType::SupportGrantOpen,
                dedupeKey: $dedupeKey,
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.support_grant_summary',
                params: ['count' => $active],
                organizationId: $organizationId,
                linkRoute: 'admin.support.grants.index',
                notify: false, // Freigabe-UI benachrichtigt bereits selbst
            ));
        } else {
            $alerts->resolve($dedupeKey);
        }
    }

    private function scanProblemReports(OperationsAlertService $alerts, int $organizationId): void {
        $open = ProblemReport::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', '!=', ProblemReportStatus::Closed->value)
            ->count();

        $dedupeKey = "problem_report_open:org:{$organizationId}";
        if ($open > 0) {
            $alerts->report(new OperationsSignal(
                type: OperationsTaskType::ProblemReportOpen,
                dedupeKey: $dedupeKey,
                severity: OperationsTaskSeverity::Warning,
                titleKey: 'operations.task.problem_report_summary',
                params: ['count' => $open],
                organizationId: $organizationId,
                linkRoute: 'admin.problem-reports.index',
                notify: false, // Eingang meldet bereits einzeln (info)
            ));
        } else {
            $alerts->resolve($dedupeKey);
        }
    }
}
