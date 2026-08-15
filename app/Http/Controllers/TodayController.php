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

use App\Enums\Project\ProjectStatus;
use App\Http\Controllers\Concerns\{ProvidesTimeEntryTagPicker, ResolvesGlobalDateRange};
use App\Models\{Attendance, Project, TimeEntry, User};
use App\Services\Attendance\AttendanceClockService;
use App\Services\Flextime\FlexCalculator;
use App\Services\TimeApproval\{DayCloseService, UntrackedBlockCalculator};
use App\Services\Timesheet\Stopwatch;
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
    use ProvidesTimeEntryTagPicker;
    use ResolvesGlobalDateRange;

    public function __construct(
        protected AttendanceClockService $clock,
        protected FlexCalculator $flex,
        protected DayCloseService $dayClose,
        protected Stopwatch $stopwatch,
    ) {}

    public function show(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        $rawDay = $request->date('date');
        if ($rawDay !== null) {
            $day = CarbonImmutable::instance($rawDay)->startOfDay();
        } else {
            // Header-Zeitraumfilter: umfasst er genau einen Tag (Preset „Heute"
            // oder Von=Bis), folgt die Seite diesem Tag; Bereiche → heute.
            $range = $this->globalDateRange();
            $day = $range['from']->isSameDay($range['to'])
                ? $range['from']->startOfDay()
                : CarbonImmutable::today();
        }

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

        // Quick-Buchung (Rang 37): offene Zeitblöcke des Tages + Buchungsziele.
        // Nur für vergangene/heutige eigene Tage sinnvoll.
        $isFuture = $day->startOfDay()->greaterThan(CarbonImmutable::now()->endOfDay());
        $openBlocks = $isFuture
            ? []
            : app(UntrackedBlockCalculator::class)->blocks($attendances, $entries, CarbonImmutable::now());
        // Eine Liste für Quick-Buchung UND Eingabeleiste (zuletzt genutzte zuerst).
        $recentProjectIds = $isFuture ? collect() : $this->recentProjectIds($user);
        $projects = $isFuture ? collect() : $this->quickBookProjects($recentProjectIds);
        // Bei vielen Projekten bleibt die Quick-Buchung nur übersichtlich, wenn
        // Drag-Ziele auf die relevanten Projekte begrenzt sind; das Dropdown
        // gruppiert x-project-options („Zuletzt verwendet" + Kunden-Optgroups).
        $recentProjects = $projects->filter(fn(Project $p): bool => $recentProjectIds->contains($p->id))->values();
        $recentEntryTexts = $isFuture ? collect() : $this->recentEntryTexts($user, $projects);

        return view('today.show', [
            'day' => $day,
            'current' => $current,
            'openBlocks' => $openBlocks,
            'quickBookProjects' => $projects,
            'quickBookTargets' => $projects->take(10),
            'quickBookRecent' => $recentProjects,
            'entryBarProjects' => $projects,
            'entryBarRecentIds' => $recentProjectIds,
            'entryBarRecentEntries' => $recentEntryTexts,
            'allTags' => \App\Support\LookupCache::tagOptions(),
            'recentTagIds' => $this->recentTimeEntryTagSqids((int) $user->id),
            'runningEntry' => $this->stopwatch->current($user),
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
            'isFuture' => $isFuture,
            'correctionRequests' => $closure->exists ? $closure->correctionRequests()->with(['requestedBy', 'decidedBy'])->get() : collect(),
        ]);
    }

    /**
     * Zuletzt bebuchte Projekte des Nutzers (Top 10, jüngste zuerst) — Basis
     * für die Sortierung der Quick-Buchung und die „Zuletzt verwendet"-Gruppe
     * der Eingabeleiste.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function recentProjectIds(User $user): \Illuminate\Support\Collection {
        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('project_id')
            ->orderByDesc('date')
            ->limit(60)
            ->pluck('project_id')
            ->unique()
            ->take(10)
            ->values();
    }

    /**
     * Letzte unterschiedliche Buchungstexte des Nutzers (je mit Projekt) für
     * den Quick-Pick der Eingabeleiste: Tippen im Beschreibungsfeld schlägt
     * sie vor, die Übernahme setzt Text UND Projekt. Nur Projekte der aktiven
     * Auswahlliste — sonst könnte die Übernahme das Projekt nicht wählen.
     *
     * @param  \Illuminate\Support\Collection<int, Project>  $projects
     * @return \Illuminate\Support\Collection<int, TimeEntry>
     */
    private function recentEntryTexts(User $user, \Illuminate\Support\Collection $projects): \Illuminate\Support\Collection {
        $projectIds = $projects->pluck('id')->all();

        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('project_id')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderByDesc('date')
            ->limit(200)
            ->get(['description', 'project_id'])
            ->unique(fn(TimeEntry $e): string => mb_strtolower(trim((string) $e->description)) . '|' . $e->project_id)
            ->filter(fn(TimeEntry $e): bool => in_array($e->project_id, $projectIds, true))
            ->take(20)
            ->values();
    }

    /**
     * Buchungsziele für die Quick-Buchung: zuletzt genutzte Projekte des
     * Nutzers zuerst (relevante Drag-Ziele), danach die restlichen aktiven
     * Projekte der Organisation (für die vollständige Auswahl im Fallback).
     *
     * @param  \Illuminate\Support\Collection<int, int>  $recentIds
     * @return \Illuminate\Support\Collection<int, Project>
     */
    private function quickBookProjects(\Illuminate\Support\Collection $recentIds): \Illuminate\Support\Collection {
        return Project::query()
            ->where('status', ProjectStatus::Active)
            ->with('customer:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id', 'foreign_customer_id'])
            ->sortBy(fn(Project $p): int => $recentIds->search($p->id) === false ? PHP_INT_MAX : (int) $recentIds->search($p->id))
            ->values();
    }
}
