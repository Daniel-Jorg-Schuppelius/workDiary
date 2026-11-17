<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Enums\User\PermissionGroup;
use App\Modules\Manifest;

/** Modul „Auftragsbuch (Tagebuch), offene Punkte, Bereitschaft“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class DiaryManifest extends Manifest {
    public function code(): string {
        return 'diary';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Auftragsbuch (Tagebuch), offene Punkte, Bereitschaft';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Diary',
            'OpenIssue',
            'Recurrence',
            // Leistungsplan je Kunde und Periode (Feature 199).
            'ServicePlan',
            'Takeoff',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'day_closures',
            'day_correction_requests',
            'diary_entries',
            'diary_entry_events',
            'diary_entry_qualifications',
            'emergency_assignments',
            'on_call_shifts',
            'open_issue_events',
            'open_issue_follow_ups',
            'open_issues',
            'recurrence_rules',
            // Leistungsplan (Feature 199): „entfällt“, Unterlagen/Bemerkung je Zelle, Leistungsvorlagen.
            'customer_service_period_notes',
            'customer_service_waivers',
            'service_templates',
            'takeoff_lines',
            'takeoff_transfers',
            'takeoffs',
            'tour_events',
            'tour_proposals',
            'tour_stops',
            'tours',
        ];
    }

    /** @return list<PermissionGroup> */
    public function permissionGroups(): array {
        return [
            PermissionGroup::Diary,
            PermissionGroup::OpenIssues,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\WorkQueue\Contracts\WorkItemSource::class => [
                \App\Services\Diary\WorkQueue\OpenIssueWorkItemSource::class,
                \App\Services\Diary\WorkQueue\AssignedEntryWorkItemSource::class,
                // Leistungsplan (Feature 199, MVP-1639): überfällige Perioden ohne Auftrag.
                \App\Services\ServicePlan\WorkQueue\OverduePeriodWorkItemSource::class,
            ],
            // Vertretung (Feature 202): zugewiesene Aufträge übernimmt die Vertretung.
            \App\Services\Delegation\Contracts\DelegableWork::class => [
                \App\Services\Diary\WorkQueue\AssignedEntryWorkItemSource::class,
                // Leistungsplan (Feature 199, Anschluss MVP-1648): Perioden der zuständigen Person.
                \App\Services\ServicePlan\WorkQueue\OverduePeriodWorkItemSource::class,
            ],
            // Endgültige Übergabe und Austritt (Feature 202/126): zugewiesene Aufträge an eine Nachfolge.
            \App\Services\Org\Contracts\OffboardingHandover::class => [
                \App\Services\Diary\Offboarding\AssignedOrderOffboardingHandover::class,
            ],
            \App\Services\Mcp\Contracts\McpTool::class => [
                \App\Services\Diary\Mcp\OrdersTool::class,
                \App\Services\Diary\Mcp\ScheduleTool::class,
            ],
            \App\Services\Notification\DeadlineScans\DeadlineScan::class => [
                \App\Services\Diary\DeadlineScans\OpenIssueDeadlineScan::class,
            ],
            \App\Services\Search\Indexing\Sources\SearchSource::class => [
                \App\Services\Diary\Search\DiaryEntrySource::class,
                \App\Services\Diary\Search\OpenIssueSource::class,
            ],
            \App\Services\Sync\Contracts\SyncCommandHandler::class => [
                \App\Services\Diary\Sync\DiaryCommentSyncHandler::class,
                \App\Services\Takeoff\Sync\TakeoffLineSyncHandler::class,
            ],
            \App\Services\Billing\Contracts\DocumentChainSource::class => [
                \App\Services\Takeoff\Chain\TakeoffsWithoutTransfer::class,
            ],
            \App\Automation\Actions\RuleAction::class => [
                \App\Services\Diary\Automation\CreateFollowUpOrderAction::class,
            ],
            \App\Automation\Triggers\RuleTrigger::class => [
                \App\Services\OpenIssue\Automation\OpenIssueCreatedTrigger::class,
            ],
            // MVP-1597: offene Punkte an Kunde, Projekt und Asset.
            \App\Services\Cockpit\Contracts\CockpitPanel::class => [
                \App\Services\Diary\Cockpit\OpenIssueCockpitPanel::class,
                // Feature 199: Leistungsplan des Kunden.
                \App\Services\ServicePlan\Cockpit\ServicePlanCockpitPanel::class,
                // Feature 201 (MVP-1646): offene Merker an Aufträgen mit Periode.
                \App\Services\Diary\Cockpit\PeriodOrderCarryForwardCockpitPanel::class,
            ],
            // Feature 198 (MVP-1636): Aufträge zur internen Prüfung im Genehmigungs-Eingang.
            \App\Services\Approval\Contracts\ApprovalInboxSubject::class => [
                \App\Services\Diary\Approvals\OrderReviewInboxSubject::class,
            ],
            // Feature 198 (MVP-1634): Eintragsarten mit Perioden- und Prüfpflicht aus dem Branchenprofil.
            \App\Services\Classification\Contracts\ProfileInstallStep::class => [
                \App\Services\Diary\Install\EntryTypeRuleInstallStep::class,
                // Feature 199 (MVP-1674): Leistungsvorlagen — nach den Eintragsarten.
                \App\Services\ServicePlan\Install\ServiceCatalogInstallStep::class,
            ],
            // Feature 198/201: Periodenauftrag mit Prüfung und Merker zur Vorführung.
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Diary\Demo\PeriodOrderDemoBlock::class,
                // Feature 199: Leistungsplan mit erfundenen Kunden.
                \App\Services\ServicePlan\Demo\ServicePlanDemoBlock::class,
            ],
        ];
    }

    /** @return array<class-string, class-string> */
    public function contracts(): array {
        return [
            \App\Services\Takeoff\Contracts\TakeoffProgressRecorder::class => \App\Services\Takeoff\Contracts\NullTakeoffProgressRecorder::class,
            // MVP-1184: Erfahrungswert der Servicedauer kommt aus der Planung (Tour-Ist), sonst keiner.
            \App\Services\Diary\Contracts\ServiceDurationSource::class => \App\Services\Diary\Contracts\NullServiceDurationSource::class,
            // MVP-1514: Qualifikationssperre kommt aus der Planung, ohne sie keine Sperre.
            \App\Services\Diary\Contracts\AssignmentQualificationGuard::class => \App\Services\Diary\Contracts\NullAssignmentQualificationGuard::class,
            // MVP-1549: Ticket aus einem offenen Punkt legt der Helpdesk an, ohne ihn gibt es das Angebot nicht.
            \App\Services\Diary\Contracts\IssueTicketOpener::class => \App\Services\Diary\Contracts\NullIssueTicketOpener::class,
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            \App\Events\Time\TimeEntryCreated::class => [
                \App\Listeners\Diary\StartOrderFromTimeEntry::class,
            ],
            // MVP-1596: der Beleg setzt „Berechnet“ und nimmt es mit seinem Storno zurück.
            \App\Events\Invoicing\InvoiceIssued::class => [
                \App\Listeners\Diary\MarkOrdersInvoiced::class,
            ],
            \App\Events\Finance\BillingTransferCompleted::class => [
                \App\Listeners\Diary\MarkOrdersInvoiced::class,
            ],
            \App\Events\Invoicing\InvoiceCancelled::class => [
                \App\Listeners\Diary\RevertOrdersInvoiced::class,
            ],
            \App\Events\Finance\BillingTransferCancelled::class => [
                \App\Listeners\Diary\RevertOrdersInvoiced::class,
            ],
        ];
    }
}
