<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DutyPlan;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\User;
use App\Models\Vacation;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Generates printable HTML views (A4/A3) for duty plans, on-call schedules
 * and vacation overviews. Layouts use `window.print()` so users can save as
 * PDF directly from the browser. A future phase may add server-side PDF.
 *
 * Anonymisation: append `?anonymous=1` to any route to display only initials.
 */
class PrintController extends Controller
{
    public function __construct(private readonly HolidayService $holidays) {}

    /**
     * A3 querformat — Monats-Aushang: alle Mitarbeiter × alle Tage des Plans.
     */
    public function dutyPlanRoster(Request $request, DutyPlan $dutyPlan): View
    {
        Gate::authorize('view', $dutyPlan);

        $dutyPlan->load(['shifts.user:id,name,organization_id', 'shifts.shiftType']);

        /** @var Collection<int, ScheduledShift> $allShifts */
        $allShifts = $dutyPlan->shifts;

        $dates = $this->datesBetween($dutyPlan->from_date, $dutyPlan->to_date);
        $users = $this->usersForShifts($allShifts);

        // Map [user_id][date] => Collection<ScheduledShift>
        $matrix = [];
        foreach ($allShifts as $shift) {
            if ($shift->user === null) {
                continue;
            }
            $matrix[$shift->user_id][$shift->date->toDateString()][] = $shift;
        }

        return view('print.duty_plan_a3_roster', [
            'pageSize' => 'A3 landscape',
            'pageMargin' => '7mm',
            'title' => __('Dienstplan-Aushang').' — '.$dutyPlan->title,
            'dutyPlan' => $dutyPlan,
            'dates' => $dates,
            'users' => $users,
            'matrix' => $matrix,
            'shiftTypes' => $this->shiftTypesFromShifts($allShifts),
            'holidays' => $this->holidays,
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => route('duty-plans.show', $dutyPlan),
            'org' => $dutyPlan->organization?->name,
        ]);
    }

    /**
     * A4 querformat — Wochenplan: alle Mitarbeiter × 7 Tage einer Kalenderwoche.
     */
    public function dutyPlanWeek(Request $request, DutyPlan $dutyPlan): View
    {
        Gate::authorize('view', $dutyPlan);

        $anchor = $this->parseDate($request->query('date'), $dutyPlan->from_date);
        $from = $anchor->startOfWeek();
        $to = $from->endOfWeek();

        $dutyPlan->load(['shifts.user:id,name,organization_id', 'shifts.shiftType']);

        $shifts = $dutyPlan->shifts->filter(
            fn (ScheduledShift $s): bool => $s->date->between($from, $to)
        )->values();

        $dates = $this->datesBetween($from, $to);
        $users = $this->usersForShifts($shifts);

        $matrix = [];
        foreach ($shifts as $shift) {
            if ($shift->user === null) {
                continue;
            }
            $matrix[$shift->user_id][$shift->date->toDateString()][] = $shift;
        }

        return view('print.duty_plan_a4_week', [
            'pageSize' => 'A4 landscape',
            'pageMargin' => '8mm',
            'title' => __('Wochenplan').' KW '.$from->weekOfYear.' / '.$from->year,
            'dutyPlan' => $dutyPlan,
            'from' => $from,
            'to' => $to,
            'dates' => $dates,
            'users' => $users,
            'matrix' => $matrix,
            'shiftTypes' => $this->shiftTypesFromShifts($shifts),
            'holidays' => $this->holidays,
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => route('duty-plans.show', $dutyPlan),
            'org' => $dutyPlan->organization?->name,
        ]);
    }

    /**
     * A4 hoch — Tagesbriefing: ein Tag, alle Schichten + 24h-Zeitstrahl.
     */
    public function dutyPlanDayBriefing(Request $request, DutyPlan $dutyPlan): View
    {
        Gate::authorize('view', $dutyPlan);

        $date = $this->parseDate($request->query('date'), $dutyPlan->from_date);

        $dutyPlan->load(['shifts.user:id,name,organization_id', 'shifts.shiftType']);

        $shifts = $dutyPlan->shifts
            ->filter(fn (ScheduledShift $s): bool => $s->date->isSameDay($date))
            ->sortBy([fn (ScheduledShift $a, ScheduledShift $b) => strcmp(
                (string) $a->resolvedStartTime(),
                (string) $b->resolvedStartTime(),
            )])
            ->values();

        return view('print.duty_plan_a4_day_briefing', [
            'pageSize' => 'A4 portrait',
            'pageMargin' => '10mm',
            'title' => __('Tagesbriefing').' '.$date->translatedFormat('l, d.m.Y'),
            'dutyPlan' => $dutyPlan,
            'date' => $date,
            'shifts' => $shifts,
            'holidayName' => $this->holidays->nameFor($date),
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => route('duty-plans.show', $dutyPlan),
            'org' => $dutyPlan->organization?->name,
        ]);
    }

    /**
     * A4 hoch — Mitarbeiter-Monatszettel: ein Mitarbeiter, ganzer Monat als Liste.
     */
    public function userMonth(Request $request, User $user): View
    {
        /** @var ?User $actor */
        $actor = Auth::user();
        abort_unless($actor !== null && ($actor->id === $user->id || $actor->isAdmin()), 403);

        $month = $this->parseDate($request->query('month'), CarbonImmutable::now())->startOfMonth();
        $end = $month->endOfMonth();

        $shifts = ScheduledShift::query()
            ->with('shiftType')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$month->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $vacations = Vacation::query()
            ->where('user_id', $user->id)
            ->where('end_date', '>=', $month->toDateString())
            ->where('start_date', '<=', $end->toDateString())
            ->get();

        return view('print.duty_plan_a4_user_month', [
            'pageSize' => 'A4 portrait',
            'pageMargin' => '10mm',
            'title' => __('Monatsplan').' — '.($request->boolean('anonymous') ? printable_initials($user->name) : $user->name),
            'user' => $user,
            'month' => $month,
            'end' => $end,
            'dates' => $this->datesBetween($month, $end),
            'shifts' => $shifts->groupBy(fn (ScheduledShift $s) => $s->date->toDateString()),
            'vacations' => $vacations,
            'holidays' => $this->holidays,
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => url()->previous(),
            'org' => $user->organization?->name,
        ]);
    }

    /**
     * A4 querformat — Bereitschafts- & Notdienstplan über einen Zeitraum.
     */
    public function onCall(Request $request): View
    {
        /** @var ?User $actor */
        $actor = Auth::user();
        abort_unless($actor?->isAdmin() === true, 403);

        $from = $this->parseDate($request->query('from'), CarbonImmutable::now()->startOfMonth());
        $to = $this->parseDate($request->query('to'), $from->endOfMonth());

        $shifts = OnCallShift::query()
            ->with('user:id,name')
            ->overlapping($from->startOfDay(), $to->endOfDay())
            ->orderBy('start_at')
            ->get();

        $assignments = EmergencyAssignment::query()
            ->with('user:id,name')
            ->overlapping($from->startOfDay(), $to->endOfDay())
            ->orderBy('start_at')
            ->get();

        return view('print.on_call_a4', [
            'pageSize' => 'A4 landscape',
            'pageMargin' => '8mm',
            'title' => __('Bereitschafts- und Notdienstplan'),
            'subtitle' => $from->format('d.m.Y').' – '.$to->format('d.m.Y'),
            'from' => $from,
            'to' => $to,
            'shifts' => $shifts,
            'assignments' => $assignments,
            'holidays' => $this->holidays,
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => route('duties.index'),
        ]);
    }

    /**
     * A4 hoch — Urlaubsübersicht für ein Jahr (alle Mitarbeiter × Monate, Kalender-Streifen).
     */
    public function vacationYear(Request $request): View
    {
        /** @var ?User $actor */
        $actor = Auth::user();
        abort_unless($actor?->isAdmin() === true, 403);

        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);
        $from = CarbonImmutable::create($year, 1, 1);
        $to = CarbonImmutable::create($year, 12, 31);

        $vacations = Vacation::query()
            ->with('user:id,name')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString())
            ->whereIn('status', [Vacation::STATUS_APPROVED, Vacation::STATUS_PENDING])
            ->orderBy('start_date')
            ->get();

        $users = $vacations->pluck('user')->filter()->unique('id')->sortBy('name')->values();

        return view('print.vacation_year_a4', [
            'pageSize' => 'A4 portrait',
            'pageMargin' => '8mm',
            'title' => __('Urlaubsübersicht').' '.$year,
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'vacations' => $vacations,
            'holidays' => $this->holidays,
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => route('duties.index', ['tab' => 'urlaub']),
        ]);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function datesBetween(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $out = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $out[] = $d->toDateString();
        }

        return $out;
    }

    /**
     * @param  Collection<int, ScheduledShift>  $shifts
     * @return Collection<int, User>
     */
    private function usersForShifts(Collection $shifts): Collection
    {
        return $shifts
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * @param  Collection<int, ScheduledShift>  $shifts
     * @return Collection<int, ShiftType>
     */
    private function shiftTypesFromShifts(Collection $shifts): Collection
    {
        return $shifts
            ->pluck('shiftType')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function parseDate(?string $input, CarbonImmutable|\DateTimeInterface $fallback): CarbonImmutable
    {
        if (! $input) {
            return $fallback instanceof CarbonImmutable
                ? $fallback
                : CarbonImmutable::instance($fallback);
        }
        try {
            return CarbonImmutable::parse($input);
        } catch (\Throwable) {
            return $fallback instanceof CarbonImmutable
                ? $fallback
                : CarbonImmutable::instance($fallback);
        }
    }
}
