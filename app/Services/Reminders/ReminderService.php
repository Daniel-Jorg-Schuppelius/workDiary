<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Reminders;

use App\Enums\Expense\{ExpenseStatus, PerDiemTripStatus};
use App\Enums\Vacation\VacationStatus;
use App\Models\{Expense, PerDiemTrip, User, Vacation};
use App\Support\Reminders\ReminderItem;
use Carbon\CarbonImmutable;

/**
 * Berechnet kontextsensitive Reminder für einen Benutzer.
 *
 * MVP-Regelwerk:
 *  - Spesen-Drafts älter als 7 Tage  → "Spesen einreichen" (warn)
 *  - Per-Diem-Reisen-Drafts älter 7 → "Reisekostenabrechnungen abschließen" (warn)
 *  - Urlaubsanträge des Users in Pending (info)
 *  - Für Approver: offene Pending-Spesen älter 3 Tage → "Spesen entscheiden" (error)
 *  - Für Approver: offene Pending-Urlaubsanträge älter 3 Tage (error)
 */
class ReminderService {
    private const DRAFT_AGE_DAYS = 7;

    private const PENDING_AGE_DAYS = 3;

    /**
     * @return list<ReminderItem>
     */
    public function for(User $user): array {
        $now = CarbonImmutable::now();
        $orgId = $user->organization_id;
        /** @var list<ReminderItem> $items */
        $items = [];

        // ── Eigene Spesen-Drafts ────────────────────────────────────────────
        $oldDraftExpenses = Expense::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('status', ExpenseStatus::Draft)
            ->where('created_at', '<', $now->subDays(self::DRAFT_AGE_DAYS))
            ->count();
        if ($oldDraftExpenses > 0) {
            $items[] = new ReminderItem(
                key: 'expense_drafts',
                title: trans_choice(
                    '{1}:n Spesen-Entwurf wartet auf Abgabe|[2,*]:n Spesen-Entwürfe warten auf Abgabe',
                    $oldDraftExpenses,
                    ['n' => $oldDraftExpenses],
                ),
                description: __('Älter als :d Tage. Bitte einreichen oder löschen.', ['d' => self::DRAFT_AGE_DAYS]),
                url: route('expenses.index', ['status' => ExpenseStatus::Draft->value]),
                icon: 'receipt_long',
                severity: 'warning',
                count: $oldDraftExpenses,
            );
        }

        // ── Eigene Per-Diem-Drafts ──────────────────────────────────────────
        $oldDraftTrips = PerDiemTrip::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('status', PerDiemTripStatus::Draft)
            ->where('created_at', '<', $now->subDays(self::DRAFT_AGE_DAYS))
            ->count();
        if ($oldDraftTrips > 0) {
            $items[] = new ReminderItem(
                key: 'per_diem_drafts',
                title: trans_choice(
                    '{1}:n Reise-Entwurf offen|[2,*]:n Reise-Entwürfe offen',
                    $oldDraftTrips,
                    ['n' => $oldDraftTrips],
                ),
                description: __('Verpflegungspauschalen erfassen und in Spese umwandeln.'),
                url: route('per-diem-trips.index'),
                icon: 'flight',
                severity: 'warning',
                count: $oldDraftTrips,
            );
        }

        // ── Eigene Urlaubsanträge in Pending ────────────────────────────────
        $pendingOwnVacations = Vacation::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('status', VacationStatus::Pending)
            ->count();
        if ($pendingOwnVacations > 0) {
            $items[] = new ReminderItem(
                key: 'vacation_pending_own',
                title: trans_choice(
                    '{1}:n Urlaubsantrag wartet auf Entscheidung|[2,*]:n Urlaubsanträge warten auf Entscheidung',
                    $pendingOwnVacations,
                    ['n' => $pendingOwnVacations],
                ),
                description: __('Status bei deiner Führungskraft.'),
                url: route('vacations.index'),
                icon: 'beach_access',
                severity: 'info',
                count: $pendingOwnVacations,
            );
        }

        // ── Approver-Reminder ───────────────────────────────────────────────
        if ($user->isAdmin()) {
            $oldPendingExpenses = Expense::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where('status', ExpenseStatus::Pending)
                ->where('updated_at', '<', $now->subDays(self::PENDING_AGE_DAYS))
                ->count();
            if ($oldPendingExpenses > 0) {
                $items[] = new ReminderItem(
                    key: 'expense_approvals_due',
                    title: trans_choice(
                        '{1}:n Spese wartet auf Freigabe|[2,*]:n Spesen warten auf Freigabe',
                        $oldPendingExpenses,
                        ['n' => $oldPendingExpenses],
                    ),
                    description: __('Älter als :d Tage – bitte zeitnah entscheiden.', ['d' => self::PENDING_AGE_DAYS]),
                    url: route('expense-approvals.inbox'),
                    icon: 'rule',
                    severity: 'error',
                    count: $oldPendingExpenses,
                );
            }

            $oldPendingVacations = Vacation::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where('status', VacationStatus::Pending)
                ->where('created_at', '<', $now->subDays(self::PENDING_AGE_DAYS))
                ->count();
            if ($oldPendingVacations > 0) {
                $items[] = new ReminderItem(
                    key: 'vacation_approvals_due',
                    title: trans_choice(
                        '{1}:n Urlaubsantrag wartet auf Freigabe|[2,*]:n Urlaubsanträge warten auf Freigabe',
                        $oldPendingVacations,
                        ['n' => $oldPendingVacations],
                    ),
                    description: __('Älter als :d Tage – Team wartet auf Antwort.', ['d' => self::PENDING_AGE_DAYS]),
                    url: route('vacations.index'),
                    icon: 'event_busy',
                    severity: 'error',
                    count: $oldPendingVacations,
                );
            }
        }

        return $items;
    }
}
