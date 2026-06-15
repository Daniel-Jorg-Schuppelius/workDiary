<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodayController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Attendance, TimeEntry, User};
use App\Services\Attendance\AttendanceClockService;
use App\Services\Flextime\FlexCalculator;
use App\Services\TimeApproval\DayCloseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * „Heute" — die tägliche Selbstbedienungs-Tagesseite (eigener Tag). Seit der
 * Zusammenlegung mit dem Tagesabschluss (MVP-015) zeigt diese Seite zusätzlich
 * Pausen, Warnungen, Bilanz, Korrekturanträge und die Abschluss-Aktionen über
 * die gemeinsamen Partials unter time-approval/day/. Die Tagesabschluss-Route
 * bleibt für Fremdtage/Admin (`?user=`) erhalten.
 */
class TodayController extends Controller {
    public function __construct(
        protected AttendanceClockService $clock,
        protected FlexCalculator $flex,
        protected DayCloseService $dayClose,
    ) {}

    public function show(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        $rawDay = $request->date('date');
        $day = $rawDay !== null ? CarbonImmutable::instance($rawDay)->startOfDay() : CarbonImmutable::today();

        // Tagesabschluss-Kontext (eigener Tag): legt den Abschluss beim ersten
        // Öffnen an (Audit dayClose.opened 1×/Tag) und liefert Checks/Bilanz.
        $closure = $this->dayClose->getOrCreate($user, $day);
        Gate::authorize('view', $closure);
        $context = $this->dayClose->context($user, $day);

        /** @var \Illuminate\Support\Collection<int, Attendance> $attendances */
        $attendances = $context['attendances'];
        /** @var \Illuminate\Support\Collection<int, TimeEntry> $entries */
        $entries = $context['entries'];

        $targetMinutes = (int) $context['aggregates']['target'];
        $attendanceMinutes = (int) $attendances->sum(function (Attendance $a): int {
            if ($a->duration_minutes > 0) {
                return (int) $a->duration_minutes;
            }
            // open attendance: count from started_at to now
            if ($a->started_at) {
                $end = $a->ended_at ?? CarbonImmutable::now();
                $gross = (int) $a->started_at->diffInMinutes($end, false);

                return max(0, $gross - $a->break_minutes_total);
            }

            return 0;
        });
        $entriesMinutes = (int) $entries->sum('minutes');
        $untrackedMinutes = max(0, $attendanceMinutes - $entriesMinutes);

        // Group entries by activity for breakdown
        $byActivity = $entries->groupBy('activity_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'minutes' => (int) $group->sum('minutes'),
            ];
        });

        $current = $this->clock->current($user);

        return view('today.show', [
            'day' => $day,
            'current' => $current,
            'attendances' => $attendances,
            'entries' => $entries,
            'targetMinutes' => $targetMinutes,
            'attendanceMinutes' => $attendanceMinutes,
            'entriesMinutes' => $entriesMinutes,
            'untrackedMinutes' => $untrackedMinutes,
            'byActivity' => $byActivity,
            // Tagesabschluss-Workflow (gemeinsame Partials erwarten diese Variablen):
            'closure' => $closure,
            'isOwnDay' => true,
            'targetUser' => $user,
            'openAttendance' => $current,
            'effectiveStatus' => $this->dayClose->effectiveStatus($closure, $context['monthLocked']),
            'monthLocked' => $context['monthLocked'],
            'issues' => $context['issues'],
            'hasBlocking' => $context['hasBlocking'],
            'aggregates' => $context['aggregates'],
            'validator' => $this->dayClose->makeValidator(),
            'isToday' => $day->isSameDay(CarbonImmutable::now()),
            'isFuture' => $day->startOfDay()->greaterThan(CarbonImmutable::now()->endOfDay()),
            'correctionRequests' => $closure->exists ? $closure->correctionRequests()->with(['requestedBy', 'decidedBy'])->get() : collect(),
        ]);
    }
}
