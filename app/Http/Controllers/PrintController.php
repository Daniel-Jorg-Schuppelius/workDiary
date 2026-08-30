<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Vacation\VacationStatus;
use App\Models\{DutyPlan, EmergencyAssignment, OnCallShift, ScheduledShift, ShiftType, User, Vacation};
use App\Services\HolidayService;
use Carbon\{CarbonImmutable, CarbonPeriod};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Generates printable HTML views (A4/A3) for duty plans, on-call schedules
 * and vacation overviews. Layouts use `window.print()` so users can save as
 * PDF directly from the browser. A future phase may add server-side PDF.
 *
 * Anonymisation: append `?anonymous=1` to any route to display only initials.
 */
class PrintController extends Controller {
    use \App\Http\Controllers\Concerns\ResolvesCurrentOrganization;

    public function __construct(private readonly HolidayService $holidays) {}

    /**
     * A3 querformat — Monats-Aushang: alle Mitarbeiter × alle Tage des Plans.
     */
    public function dutyPlanRoster(Request $request, DutyPlan $dutyPlan): View {
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
            'title' => __('Dienstplan-Aushang') . ' — ' . $dutyPlan->title,
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
    public function dutyPlanWeek(Request $request, DutyPlan $dutyPlan): View {
        Gate::authorize('view', $dutyPlan);

        $anchor = $this->parseDate($request->query('date'), $dutyPlan->from_date);
        $from = $anchor->startOfWeek();
        $to = $from->endOfWeek();

        $dutyPlan->load(['shifts.user:id,name,organization_id', 'shifts.shiftType']);

        $shifts = $dutyPlan->shifts->filter(
            fn(ScheduledShift $s): bool => $s->date->between($from, $to)
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
            'title' => __('Wochenplan') . ' KW ' . $from->weekOfYear . ' / ' . $from->year,
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
    public function dutyPlanDayBriefing(Request $request, DutyPlan $dutyPlan): View {
        Gate::authorize('view', $dutyPlan);

        $date = $this->parseDate($request->query('date'), $dutyPlan->from_date);

        $dutyPlan->load(['shifts.user:id,name,organization_id', 'shifts.shiftType']);

        $shifts = $dutyPlan->shifts
            ->filter(fn(ScheduledShift $s): bool => $s->date->isSameDay($date))
            ->sortBy([fn(ScheduledShift $a, ScheduledShift $b) => strcmp(
                (string) $a->resolvedStartTime(),
                (string) $b->resolvedStartTime(),
            )])
            ->values();

        return view('print.duty_plan_a4_day_briefing', [
            'pageSize' => 'A4 portrait',
            'pageMargin' => '10mm',
            'title' => __('Tagesbriefing') . ' ' . $date->translatedFormat('l, d.m.Y'),
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
    public function userMonth(Request $request, User $user): View {
        /** @var ?User $actor */
        $actor = Auth::user();
        abort_unless($actor !== null && ($actor->id === $user->id || $actor->isAdmin()), 403);
        // Der Name des Mitarbeiters steht im Titel: ohne Org-Vergleich wäre
        // der Zettel eine Namensauskunft über fremde Mandanten
        // (Sicherheitsscan 2026-08-23, S-06). Verglichen wird gegen die
        // AKTIVE Organisation, nicht gegen die Heimat-Org des Aufrufers —
        // sonst spränge der Zettel für einen Betreiber im Org-Wechsel auf 403.
        abort_unless((int) $user->organization_id === (int) $this->currentOrganization()->id, 403);

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
            'title' => __('Monatsplan') . ' — ' . ($request->boolean('anonymous') ? StringHelper::printableInitials($user->name) : $user->name),
            'user' => $user,
            'month' => $month,
            'end' => $end,
            'dates' => $this->datesBetween($month, $end),
            'shifts' => $shifts->groupBy(fn(ScheduledShift $s) => $s->date->toDateString()),
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
    public function onCall(Request $request): View {
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
            'subtitle' => $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y'),
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
     * A4 quer — Schichtplan-Aushang: alle Mitarbeiter × Tage des Zeitraums,
     * Zellen mit Schichtart-Kürzel und Zeit. Zeitraum via from/to (Default:
     * aktueller Monat).
     */
    public function schedule(Request $request): View {
        /** @var ?User $actor */
        $actor = Auth::user();
        abort_unless($actor?->isAdmin() === true, 403);

        $from = $this->parseDate($request->query('from'), CarbonImmutable::now()->startOfMonth());
        $to = $this->parseDate($request->query('to'), $from->endOfMonth());
        if ($to->lessThan($from)) {
            $to = $from->endOfMonth();
        }

        $shifts = ScheduledShift::query()
            ->with(['user:id,name', 'shiftType'])
            ->forDateRange($from, $to)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Mitarbeiter mit Schichten im Zeitraum (nach Name sortiert).
        $users = $shifts->map(fn(ScheduledShift $s): ?User => $s->user)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        // Map "userId|YYYY-MM-DD" => Collection<ScheduledShift> für O(1)-Zugriff in der Matrix.
        $byUserDate = $shifts->groupBy(fn(ScheduledShift $s): string => $s->user_id . '|' . $s->date->toDateString());

        // Aktive Schichtarten für die Legende.
        $shiftTypes = $shifts->map(fn(ScheduledShift $s): ?ShiftType => $s->shiftType)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('print.schedule_a4', [
            'pageSize' => 'A4 landscape',
            'pageMargin' => '8mm',
            'title' => __('Schichtplan'),
            'subtitle' => $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y'),
            'from' => $from,
            'to' => $to,
            'users' => $users,
            'byUserDate' => $byUserDate,
            'shiftTypes' => $shiftTypes,
            'holidays' => $this->holidays,
            'anonymous' => $request->boolean('anonymous'),
            'backUrl' => route('schedule.index'),
        ]);
    }

    /**
     * A4 hoch — Urlaubsübersicht für ein Jahr (alle Mitarbeiter × Monate, Kalender-Streifen).
     */
    public function vacationYear(Request $request): View {
        /** @var ?User $actor */
        $actor = Auth::user();
        abort_unless($actor?->isAdmin() === true, 403);

        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);
        $from = CarbonImmutable::createFromDate($year, 1, 1)->startOfDay();
        $to = CarbonImmutable::createFromDate($year, 12, 31)->endOfDay();

        $vacations = Vacation::query()
            ->with('user:id,name')
            ->where('end_date', '>=', $from->toDateString())
            ->where('start_date', '<=', $to->toDateString())
            ->whereIn('status', [VacationStatus::Approved->value, VacationStatus::Pending->value])
            ->orderBy('start_date')
            ->get();

        $users = $vacations->pluck('user')->filter()->unique('id')->sortBy('name')->values();

        return view('print.vacation_year_a4', [
            'pageSize' => 'A4 portrait',
            'pageMargin' => '8mm',
            'title' => __('Urlaubsübersicht') . ' ' . $year,
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
    private function datesBetween(\DateTimeInterface $from, \DateTimeInterface $to): array {
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
    private function usersForShifts(Collection $shifts): Collection {
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
    private function shiftTypesFromShifts(Collection $shifts): Collection {
        return $shifts
            ->pluck('shiftType')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function parseDate(?string $input, CarbonImmutable|\DateTimeInterface $fallback): CarbonImmutable {
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
