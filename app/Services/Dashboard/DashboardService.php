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

/**
 * Datenquelle des Dashboards.
 *
 * Seit der Umstellung auf abwählbare Kacheln holt sich jede Kachel genau den
 * Ausschnitt, den sie zeigt — ausgeblendete Kacheln kosten dadurch keine
 * Query. Damit mehrere Kacheln dieselbe Abfrage nicht doppelt auslösen,
 * merkt sich der Service jedes Ergebnis pro Request ($memo); der Service ist
 * dafür scoped gebunden (AppServiceProvider), niemals als Singleton — sonst
 * würde ein Queue-Worker Daten des ersten Nutzers weiterreichen.
 */
class DashboardService {
    /** @var array<string, mixed> */
    private array $memo = [];

    private ?CarbonImmutable $now = null;

    public function __construct(
        private readonly OnboardingChecklistResolver $onboardingResolver,
    ) {}

    /**
     * Gesamtbild für die API (api.dashboard) und Aufrufer, die alles auf
     * einmal brauchen. Die Kacheln nutzen stattdessen die Einzelmethoden.
     *
     * @return array<string, mixed>
     */
    public function summarize(User $user, ?CarbonImmutable $now = null): array {
        $now = $this->now($now);

        return [
            'now' => $now,
            'user' => $this->personal($user),
            'team' => $user->isAdmin() ? $this->team() : null,
            'finance' => $this->finance($user),
            'onboarding' => $this->onboarding($user),
        ];
    }

    /**
     * Zeitbasis des Requests. Einmal gesetzt, bleibt sie für alle Kacheln
     * gleich — sonst rutschen Tagesgrenzen zwischen zwei Kacheln auseinander.
     */
    public function now(?CarbonImmutable $now = null): CarbonImmutable {
        if ($now !== null) {
            $this->now = $now;
        }

        return $this->now ??= CarbonImmutable::now();
    }

    /**
     * @return array{checklist: array<string,mixed>, widget_dismissed_at: ?string}|null
     */
    public function onboarding(User $user): ?array {
        return $this->remember('onboarding', $user, function () use ($user): ?array {
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
        });
    }

    /**
     * Persönlicher Block als Ganzes (API/summarize).
     *
     * @return array<string, mixed>
     */
    public function personal(User $user): array {
        return [
            'kpi' => $this->personalKpis($user),
            'today_shifts' => $this->todayShifts($user),
            'upcoming_shifts' => $this->upcomingShifts($user)->take($this->recentLimit()),
            'upcoming_emergencies' => $this->upcomingEmergencies($user)->take($this->recentLimit()),
            'recent_entries' => $this->recentEntries($user),
            'recent_comments' => $this->recentComments($user),
            'recent_attachments' => $this->recentAttachments($user),
            'upcoming_scheduled' => $this->scheduledShifts($user),
            'open_issues_assigned' => $this->openIssuesAssigned($user),
            'window_end' => $this->now()->addDays(7),
        ];
    }

    /**
     * @return array{open_entries:int, progress_entries:int, upcoming_shifts:int, upcoming_emergencies:int, open_issues_assigned:int, open_issues_created:int}
     */
    public function personalKpis(User $user): array {
        return $this->remember('personalKpis', $user, function () use ($user): array {
            // Einzel-Query statt 2× COUNT für Diary-Einträge
            /** @var object{open_cnt: int|string, progress_cnt: int|string}|null $entryCounts */
            $entryCounts = DiaryEntry::query()
                ->where('user_id', $user->id)
                ->where('is_archived', false)
                ->selectRaw('SUM(status IN (2,3)) as open_cnt, SUM(status = 1) as progress_cnt')
                ->first();

            return [
                'open_entries' => (int) $entryCounts?->open_cnt,
                'progress_entries' => (int) $entryCounts?->progress_cnt,
                'upcoming_shifts' => $this->upcomingShifts($user)->count(),
                'upcoming_emergencies' => $this->upcomingEmergencies($user)->count(),
                'open_issues_assigned' => OpenIssue::query()
                    ->where('assignee_user_id', $user->id)
                    ->whereNull('closed_at')
                    ->count(),
                'open_issues_created' => OpenIssue::query()
                    ->where('created_by_user_id', $user->id)
                    ->whereNull('closed_at')
                    ->count(),
            ];
        });
    }

    /** @return Collection<int, OnCallShift> */
    public function upcomingShifts(User $user): Collection {
        return $this->remember('upcomingShifts', $user, fn (): Collection => OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $this->now())
            ->orderBy('start_at')
            ->get());
    }

    /** @return Collection<int, EmergencyAssignment> */
    public function upcomingEmergencies(User $user): Collection {
        return $this->remember('upcomingEmergencies', $user, fn (): Collection => EmergencyAssignment::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('end_at', '>=', $this->now())
            ->orderBy('start_at')
            ->get());
    }

    /** @return Collection<int, OnCallShift> */
    public function todayShifts(User $user): Collection {
        return $this->remember('todayShifts', $user, fn (): Collection => OnCallShift::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->overlapping($this->now()->startOfDay()->toDateTime(), $this->now()->endOfDay()->toDateTime())
            ->orderBy('start_at')
            ->get());
    }

    /** @return Collection<int, ScheduledShift> */
    public function scheduledShifts(User $user): Collection {
        return $this->remember('scheduledShifts', $user, fn (): Collection => ScheduledShift::query()
            ->where('user_id', $user->id)
            ->forDateRange($this->now()->toDateString(), $this->now()->addDays(7)->toDateString())
            ->visible()
            ->with('shiftType:id,name,abbreviation,color')
            ->orderBy('date')
            ->limit(7)
            ->get());
    }

    /** @return Collection<int, DiaryEntry> */
    public function recentEntries(User $user): Collection {
        return $this->remember('recentEntries', $user, fn (): Collection => DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->select(['id', 'user_id', 'content', 'status', 'start_at', 'updated_at'])
            ->latest('updated_at')
            ->limit($this->recentLimit())
            ->get());
    }

    /** @return Collection<int, Comment> */
    public function recentComments(User $user): Collection {
        return $this->remember('recentComments', $user, fn (): Collection => Comment::query()
            ->where('commentable_type', DiaryEntry::class)
            // Subquery statt pluck() + large IN-Klausel
            ->whereIn('commentable_id', DiaryEntry::query()->where('user_id', $user->id)->select('id'))
            ->with(['user:id,name', 'commentable:id,content,user_id'])
            ->latest()
            ->limit($this->recentLimit())
            ->get());
    }

    /** @return Collection<int, Attachment> */
    public function recentAttachments(User $user): Collection {
        return $this->remember('recentAttachments', $user, fn (): Collection => Attachment::query()
            ->where('attachable_type', DiaryEntry::class)
            ->whereIn('attachable_id', DiaryEntry::query()->where('user_id', $user->id)->select('id'))
            ->with('uploader:id,name')
            ->latest()
            ->limit($this->recentLimit())
            ->get());
    }

    /** @return Collection<int, OpenIssue> */
    public function openIssuesAssigned(User $user): Collection {
        return $this->remember('openIssuesAssigned', $user, fn (): Collection => OpenIssue::query()
            ->where('assignee_user_id', $user->id)
            ->whereNull('closed_at')
            ->with(['subject', 'creator'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit($this->recentLimit())
            ->get());
    }

    /**
     * Team-Block als Ganzes (API/summarize).
     *
     * @return array<string, mixed>
     */
    public function team(): array {
        return [
            'kpi' => $this->teamKpis(),
            'recent_activity' => $this->teamActivity(),
        ];
    }

    /**
     * @return array{open_entries:int, progress_entries:int, archived_today:int, user_count:int}
     */
    public function teamKpis(): array {
        return $this->remember('teamKpis', null, function (): array {
            // Einzel-Query statt 2× COUNT
            /** @var object{open_cnt: int|string, progress_cnt: int|string}|null $entryCounts */
            $entryCounts = DiaryEntry::query()
                ->where('is_archived', false)
                ->selectRaw('SUM(status IN (2,3)) as open_cnt, SUM(status = 1) as progress_cnt')
                ->first();

            return [
                'open_entries' => (int) $entryCounts?->open_cnt,
                'progress_entries' => (int) $entryCounts?->progress_cnt,
                'archived_today' => DiaryEntry::query()
                    ->where('is_archived', true)
                    ->whereBetween('archived_at', [$this->now()->startOfDay(), $this->now()->endOfDay()])
                    ->count(),
                'user_count' => User::inCurrentOrganization()->count(),
            ];
        });
    }

    /** @return Collection<int, Comment> */
    public function teamActivity(): Collection {
        return $this->remember('teamActivity', null, fn (): Collection => Comment::query()
            ->where('commentable_type', DiaryEntry::class)
            ->select(['id', 'user_id', 'commentable_type', 'commentable_id', 'body', 'created_at'])
            ->with(['user:id,name', 'commentable:id,content'])
            ->latest()
            ->limit(8)
            ->get());
    }

    /**
     * Finanz- und Reise-KPIs: Monat-to-Date für den Benutzer + (für Admins) für
     * den Approval-Stack der Organisation.
     *
     * @return array<string, mixed>
     */
    public function finance(User $user): array {
        return $this->remember('finance', $user, function () use ($user): array {
            $now = $this->now();
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
                'vacation' => $this->vacation($user),
                'approver_pending' => $this->approverPending($user),
            ];
        });
    }

    /**
     * Was an Spesen und Urlaubsanträgen auf eine Entscheidung wartet — nur
     * für Genehmigende, sonst null.
     *
     * @return array{expenses:int, vacations:int}|null
     */
    public function approverPending(User $user): ?array {
        return $this->remember('approverPending', $user, function () use ($user): ?array {
            if (! $user->isAdmin()) {
                return null;
            }

            $orgId = $user->organization_id;

            return [
                'expenses' => Expense::query()
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where('status', ExpenseStatus::Pending)
                    ->count(),
                'vacations' => Vacation::query()
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where('status', VacationStatus::Pending)
                    ->count(),
            ];
        });
    }

    /**
     * Urlaubszahlen des Nutzers (eigene Kachel „Urlaub & Flex").
     *
     * @return array{pending:int, approved_days_this_year:float}
     */
    public function vacation(User $user): array {
        return $this->remember('vacation', $user, function () use ($user): array {
            $orgId = $user->organization_id;

            $approvedDays = Vacation::query()
                ->where('user_id', $user->id)
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where('status', VacationStatus::Approved)
                ->whereYear('start_date', $this->now()->year)
                ->get(['start_date', 'end_date'])
                ->sum(fn(Vacation $v) => $v->start_date->diffInDays($v->end_date) + 1);

            return [
                'pending' => Vacation::query()
                    ->where('user_id', $user->id)
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where('status', VacationStatus::Pending)
                    ->count(),
                'approved_days_this_year' => (float) $approvedDays,
            ];
        });
    }

    private function recentLimit(): int {
        return $this->memo['recentLimit'] ??= (int) Setting::get('ui.dashboard.recent_limit', 5);
    }

    /**
     * @template T
     *
     * @param  callable():T  $resolver
     * @return T
     */
    private function remember(string $key, ?User $user, callable $resolver): mixed {
        $memoKey = $key . ':' . ($user?->getKey() ?? 'org');

        if (! array_key_exists($memoKey, $this->memo)) {
            $this->memo[$memoKey] = $resolver();
        }

        /** @var T $value */
        $value = $this->memo[$memoKey];

        return $value;
    }
}
