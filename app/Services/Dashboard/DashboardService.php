<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Dashboard;

use App\Enums\Expense\{ExpenseStatus, PerDiemTripStatus};
use App\Enums\User\Permission;
use App\Enums\Vacation\VacationStatus;
use App\Models\{Attachment, Comment, DiaryEntry, EmergencyAssignment, Expense, OnCallShift, OpenIssue, PerDiemTrip, ScheduledShift, User, Vacation};
use App\Services\Onboarding\OnboardingChecklistResolver;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService {
    public function __construct(
        private readonly OnboardingChecklistResolver $onboardingResolver,
    ) {
    }

    /** @return array<string, mixed> */
    public function summarize(User $user, ?CarbonImmutable $now = null): array {
        $now ??= CarbonImmutable::now();

        return [
            'now' => $now,
            'user' => $this->personal($user, $now),
            'team' => $user->isAdmin() ? $this->team($now) : null,
            'finance' => $this->finance($user, $now),
            'onboarding' => $this->onboarding($user),
        ];
    }

    /**
     * @return array{checklist: array<string,mixed>, widget_dismissed_at: ?string}|null
     */
    private function onboarding(User $user): ?array {
        if (! $user->can(Permission::OrgOnboardingView->value)) {
            return null;
        }

        $organization = $user->organization;
        if ($organization === null) {
            return null;
        }

        $checklist = $this->onboardingResolver->forOrganization($organization, $user);
        $widgetDismissedAt = $organization->groupSettings('ui')['onboarding_widget_dismissed_at'] ?? null;

        return [
            'checklist' => $checklist,
            'widget_dismissed_at' => is_string($widgetDismissedAt) ? $widgetDismissedAt : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personal(User $user, CarbonImmutable $now): array {
        $weekEnd = $now->addDays(7);

        // Einzel-Query statt 2× COUNT für Diary-Einträge
        /** @var object{open_cnt: int|string, progress_cnt: int|string}|null $entryCounts */
        $entryCounts = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->selectRaw('SUM(status IN (2,3)) as open_cnt, SUM(status = 1) as progress_cnt')
            ->first();

        // Upcoming Shifts: Einzel-Query, Count über ->count() der Collection
        $upcomingShifts = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->get();

        // Upcoming Emergencies: ebenso
        $upcomingEmergencies = EmergencyAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $now)
            ->orderBy('start_at')
            ->get();

        $todayShifts = OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->overlapping($now->startOfDay()->toDateTime(), $now->endOfDay()->toDateTime())
            ->orderBy('start_at')
            ->get();

        $recentLimit = (int) Setting::get('ui.dashboard.recent_limit', 5);

        $recentEntries = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->select(['id', 'user_id', 'content', 'status', 'start_at', 'updated_at'])
            ->latest('updated_at')
            ->limit($recentLimit)
            ->get();

        // Subquery statt pluck() + large IN-Klausel
        $userEntryIds = DiaryEntry::query()->where('user_id', $user->id)->select('id');

        $recentComments = Comment::query()
            ->where('commentable_type', DiaryEntry::class)
            ->whereIn('commentable_id', $userEntryIds)
            ->with(['user:id,name', 'commentable:id,content,user_id'])
            ->latest()
            ->limit($recentLimit)
            ->get();

        $recentAttachments = Attachment::query()
            ->where('attachable_type', DiaryEntry::class)
            ->whereIn('attachable_id', $userEntryIds)
            ->with('uploader:id,name')
            ->latest()
            ->limit($recentLimit)
            ->get();

        $upcomingScheduledShifts = ScheduledShift::query()
            ->where('user_id', $user->id)
            ->forDateRange($now->toDateString(), $now->addDays(7)->toDateString())
            ->visible()
            ->with('shiftType:id,name,abbreviation,color')
            ->orderBy('date')
            ->limit(7)
            ->get();

        $openIssuesAssigned = OpenIssue::query()
            ->where('assignee_user_id', $user->id)
            ->whereNull('closed_at')
            ->with(['subject', 'creator'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit($recentLimit)
            ->get();

        $openIssuesAssignedCount = OpenIssue::query()
            ->where('assignee_user_id', $user->id)
            ->whereNull('closed_at')
            ->count();

        $openIssuesCreatedCount = OpenIssue::query()
            ->where('created_by_user_id', $user->id)
            ->whereNull('closed_at')
            ->count();

        return [
            'kpi' => [
                'open_entries' => (int) $entryCounts?->open_cnt,
                'progress_entries' => (int) $entryCounts?->progress_cnt,
                'upcoming_shifts' => $upcomingShifts->count(),
                'upcoming_emergencies' => $upcomingEmergencies->count(),
                'open_issues_assigned' => $openIssuesAssignedCount,
                'open_issues_created' => $openIssuesCreatedCount,
            ],
            'today_shifts' => $todayShifts,
            'upcoming_shifts' => $upcomingShifts->take($recentLimit),
            'upcoming_emergencies' => $upcomingEmergencies->take($recentLimit),
            'recent_entries' => $recentEntries,
            'recent_comments' => $recentComments,
            'recent_attachments' => $recentAttachments,
            'upcoming_scheduled' => $upcomingScheduledShifts,
            'open_issues_assigned' => $openIssuesAssigned,
            'window_end' => $weekEnd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function team(CarbonImmutable $now): array {
        // Einzel-Query statt 2× COUNT
        /** @var object{open_cnt: int|string, progress_cnt: int|string}|null $entryCounts */
        $entryCounts = DiaryEntry::query()
            ->where('is_archived', false)
            ->selectRaw('SUM(status IN (2,3)) as open_cnt, SUM(status = 1) as progress_cnt')
            ->first();

        $archivedToday = DiaryEntry::query()
            ->where('is_archived', true)
            ->whereBetween('archived_at', [$now->startOfDay(), $now->endOfDay()])
            ->count();

        $userCount = User::query()->count();

        $recentActivity = Comment::query()
            ->where('commentable_type', DiaryEntry::class)
            ->select(['id', 'user_id', 'commentable_type', 'commentable_id', 'body', 'created_at'])
            ->with(['user:id,name', 'commentable:id,content'])
            ->latest()
            ->limit(8)
            ->get();

        return [
            'kpi' => [
                'open_entries' => (int) $entryCounts?->open_cnt,
                'progress_entries' => (int) $entryCounts?->progress_cnt,
                'archived_today' => $archivedToday,
                'user_count' => $userCount,
            ],
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Finanz- und Reise-KPIs: Monat-to-Date für den Benutzer + (für Admins) für
     * den Approval-Stack der Organisation.
     *
     * @return array<string, mixed>
     */
    private function finance(User $user, CarbonImmutable $now): array {
        $orgId = $user->organization_id;
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();

        $expenseAggregates = Expense::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->selectRaw('
                COALESCE(SUM(CASE WHEN status IN (?, ?, ?) THEN amount_gross ELSE 0 END), 0) AS submitted_gross,
                COALESCE(SUM(CASE WHEN status = ? THEN amount_gross ELSE 0 END), 0) AS reimbursed_gross,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft_count
            ', [
                ExpenseStatus::Pending->value,
                ExpenseStatus::Approved->value,
                ExpenseStatus::Reimbursed->value,
                ExpenseStatus::Reimbursed->value,
                ExpenseStatus::Pending->value,
                ExpenseStatus::Draft->value,
            ])
            ->first();

        $tripsThisMonth = PerDiemTrip::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('started_at', '>=', $monthStart)
            ->where('started_at', '<=', $monthEnd)
            ->count();

        $tripDrafts = PerDiemTrip::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('status', PerDiemTripStatus::Draft)
            ->count();

        $vacationPending = Vacation::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('status', VacationStatus::Pending)
            ->count();

        $vacationApprovedThisYear = Vacation::query()
            ->where('user_id', $user->id)
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where('status', VacationStatus::Approved)
            ->whereYear('start_date', $now->year)
            ->get(['start_date', 'end_date'])
            ->sum(fn(Vacation $v) => $v->start_date->diffInDays($v->end_date) + 1);

        $approverPending = null;
        if ($user->isAdmin()) {
            $approverPending = [
                'expenses' => Expense::query()
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where('status', ExpenseStatus::Pending)
                    ->count(),
                'vacations' => Vacation::query()
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where('status', VacationStatus::Pending)
                    ->count(),
            ];
        }

        return [
            'month' => [
                'label' => $monthStart->translatedFormat('F Y'),
                'expenses_submitted_gross' => (float) ($expenseAggregates->submitted_gross ?? 0),
                'expenses_reimbursed_gross' => (float) ($expenseAggregates->reimbursed_gross ?? 0),
                'expenses_pending_count' => (int) ($expenseAggregates->pending_count ?? 0),
                'expenses_draft_count' => (int) ($expenseAggregates->draft_count ?? 0),
                'trips_count' => $tripsThisMonth,
                'trip_drafts' => $tripDrafts,
            ],
            'vacation' => [
                'pending' => $vacationPending,
                'approved_days_this_year' => (float) $vacationApprovedThisYear,
            ],
            'approver_pending' => $approverPending,
        ];
    }
}
