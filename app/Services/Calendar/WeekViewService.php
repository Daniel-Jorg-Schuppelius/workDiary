<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WeekViewService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Calendar;

use App\Models\{DiaryEntry, EmergencyAssignment, OnCallShift, User};
use App\Support\{Setting, Tz, WeekDay};
use Carbon\{CarbonImmutable, CarbonInterface};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WeekViewService {
    /**
     * Build week-view payload for the given anchor date.
     *
     * @return array{
     *   start: CarbonImmutable,
     *   end: CarbonImmutable,
     *   days: array<int, CarbonImmutable>,
     *   shifts: Collection<int, OnCallShift>,
     *   assignments: Collection<int, EmergencyAssignment>,
     *   entries: Collection<int, DiaryEntry>,
     * }
     */
    public function build(CarbonInterface $anchor, User $user, bool $teamScope, ?int $filterUserId = null): array {
        // Tagesgrenzen in der aktiven Anzeige-Zeitzone verankern, damit Positionierung/Tagesspalten der Wanduhr
        // entsprechen. Die Werte sind echte Instants (lokale Mitternacht) – Vergleiche rechnen instant-basiert.
        $tz = Tz::current();
        $start = CarbonImmutable::instance($anchor)->setTimezone($tz)->startOfWeek(WeekDay::MONDAY)->startOfDay();
        $end = $start->addDays(7); // exclusive

        // Für DB-Queries die Fenstergrenzen nach UTC umrechnen (Spalten sind UTC).
        $startUtc = $start->setTimezone('UTC');
        $endUtc = $end->setTimezone('UTC');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = $start->addDays($i);
        }

        $shifts = OnCallShift::query()
            ->with('user:id,name')
            ->overlapping($startUtc, $endUtc)
            ->where('is_archived', false)
            ->when(! $teamScope, fn($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn($q) => $q->where('user_id', $filterUserId))
            ->orderBy('start_at')
            ->get();

        $assignments = EmergencyAssignment::query()
            ->with('user:id,name')
            ->overlapping($startUtc, $endUtc)
            ->where('is_archived', false)
            ->when(! $teamScope, fn($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn($q) => $q->where('user_id', $filterUserId))
            ->orderBy('start_at')
            ->get();

        $entries = DiaryEntry::query()
            ->with('user:id,name')
            ->where('is_archived', false)
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->whereBetween('start_at', [$startUtc, $endUtc])
                    ->orWhere(function ($q2) use ($startUtc, $endUtc) {
                        $q2->whereNotNull('end_at')
                            ->where('start_at', '<', $endUtc)
                            ->where('end_at', '>', $startUtc);
                    });
            })
            ->when(! $teamScope, fn($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn($q) => $q->where('user_id', $filterUserId))
            ->orderBy('start_at')
            ->get();

        return [
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'shifts' => $shifts,
            'assignments' => $assignments,
            'entries' => $entries,
        ];
    }

    /**
     * Liefert alle Benutzer, die in der angegebenen Woche Einträge, Shifts oder
     * Notdienste haben (für Tab-Filter in der Team-Woche).
     *
     * @return Collection<int, User>
     */
    /**
     * Mehrere Wochen in EINEM Ladevorgang (Vollscan 2026-08-23, A15): die
     * Wochenansicht rief build() bis zu zwölfmal auf — je 3 Queries. Hier
     * werden Schichten/Einsätze/Aufträge über den Gesamtzeitraum einmal
     * geladen und mit derselben Überlappungsregel je Woche verteilt.
     *
     * @param  list<CarbonInterface>  $anchors
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable, days: array<int, CarbonImmutable>, shifts: Collection<int, OnCallShift>, assignments: Collection<int, EmergencyAssignment>, entries: Collection<int, DiaryEntry>}>
     */
    public function buildMany(array $anchors, User $user, bool $teamScope, ?int $filterUserId = null): array {
        if ($anchors === []) {
            return [];
        }

        $tz = Tz::current();
        $windows = [];
        foreach ($anchors as $anchor) {
            $start = CarbonImmutable::instance($anchor)->setTimezone($tz)->startOfWeek(WeekDay::MONDAY)->startOfDay();
            $windows[] = ['start' => $start, 'end' => $start->addDays(7)];
        }
        $rangeStart = min(array_map(fn (array $w) => $w['start'], $windows))->setTimezone('UTC');
        $rangeEnd = max(array_map(fn (array $w) => $w['end'], $windows))->setTimezone('UTC');

        $userFilter = fn ($q) => $q
            ->when(! $teamScope, fn ($qq) => $qq->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn ($qq) => $qq->where('user_id', $filterUserId));

        $shifts = OnCallShift::query()->with('user:id,name')->overlapping($rangeStart, $rangeEnd)->where('is_archived', false)->tap($userFilter)->orderBy('start_at')->get();
        $assignments = EmergencyAssignment::query()->with('user:id,name')->overlapping($rangeStart, $rangeEnd)->where('is_archived', false)->tap($userFilter)->orderBy('start_at')->get();
        $entries = DiaryEntry::query()
            ->with('user:id,name')
            ->where('is_archived', false)
            ->where(function ($q) use ($rangeStart, $rangeEnd): void {
                $q->whereBetween('start_at', [$rangeStart, $rangeEnd])
                    ->orWhere(function ($q2) use ($rangeStart, $rangeEnd): void {
                        $q2->whereNotNull('end_at')->where('start_at', '<', $rangeEnd)->where('end_at', '>', $rangeStart);
                    });
            })
            ->tap($userFilter)
            ->orderBy('start_at')
            ->get();

        $overlaps = static fn ($model, CarbonImmutable $start, CarbonImmutable $end): bool => $model->start_at !== null
            && $model->start_at < $end && $model->end_at !== null && $model->end_at > $start;
        $entryInWeek = static fn (DiaryEntry $entry, CarbonImmutable $start, CarbonImmutable $end): bool => $entry->start_at !== null
            && (($entry->start_at >= $start && $entry->start_at <= $end)
                || ($entry->end_at !== null && $entry->start_at < $end && $entry->end_at > $start));

        $out = [];
        foreach ($windows as $window) {
            $start = $window['start'];
            $end = $window['end'];
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $days[$i] = $start->addDays($i);
            }
            $out[] = [
                'start' => $start,
                'end' => $end,
                'days' => $days,
                'shifts' => $shifts->filter(fn (OnCallShift $s): bool => $overlaps($s, $start, $end))->values(),
                'assignments' => $assignments->filter(fn (EmergencyAssignment $a): bool => $overlaps($a, $start, $end))->values(),
                'entries' => $entries->filter(fn (DiaryEntry $e): bool => $entryInWeek($e, $start, $end))->values(),
            ];
        }

        return $out;
    }

    /**
     * Nutzer mit Schichten/Einsätzen/Aufträgen in irgendeiner der Wochen —
     * ein Ladevorgang statt vier Queries je Woche (A15).
     *
     * @param  list<CarbonInterface>  $anchors
     * @return Collection<int, User>
     */
    public function usersInWeeks(array $anchors): Collection {
        if ($anchors === []) {
            return new Collection;
        }
        $tz = Tz::current();
        $starts = array_map(fn (CarbonInterface $a) => CarbonImmutable::instance($a)->setTimezone($tz)->startOfWeek(WeekDay::MONDAY)->startOfDay(), $anchors);
        $startUtc = min($starts)->setTimezone('UTC');
        $endUtc = max($starts)->addDays(7)->setTimezone('UTC');

        $entryUserIds = DiaryEntry::query()
            ->where('is_archived', false)
            ->where(function ($q) use ($startUtc, $endUtc): void {
                $q->whereBetween('start_at', [$startUtc, $endUtc])
                    ->orWhere(function ($q2) use ($startUtc, $endUtc): void {
                        $q2->whereNotNull('end_at')->where('start_at', '<', $endUtc)->where('end_at', '>', $startUtc);
                    });
            })
            ->distinct()
            ->pluck('user_id');
        $shiftUserIds = OnCallShift::query()->overlapping($startUtc, $endUtc)->where('is_archived', false)->distinct()->pluck('user_id');
        $assignUserIds = EmergencyAssignment::query()->overlapping($startUtc, $endUtc)->where('is_archived', false)->distinct()->pluck('user_id');

        $ids = $entryUserIds->merge($shiftUserIds)->merge($assignUserIds)->filter()->unique()->values();

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, User> */
    public function usersInWeek(CarbonInterface $anchor): Collection {
        $tz = Tz::current();
        $start = CarbonImmutable::instance($anchor)->setTimezone($tz)->startOfWeek(WeekDay::MONDAY)->startOfDay();
        $end = $start->addDays(7);
        $startUtc = $start->setTimezone('UTC');
        $endUtc = $end->setTimezone('UTC');

        $entryUserIds = DiaryEntry::query()
            ->where('is_archived', false)
            ->where(function ($q) use ($startUtc, $endUtc) {
                $q->whereBetween('start_at', [$startUtc, $endUtc])
                    ->orWhere(function ($q2) use ($startUtc, $endUtc) {
                        $q2->whereNotNull('end_at')
                            ->where('start_at', '<', $endUtc)
                            ->where('end_at', '>', $startUtc);
                    });
            })
            ->distinct()
            ->pluck('user_id');

        $shiftUserIds = OnCallShift::query()->overlapping($startUtc, $endUtc)->where('is_archived', false)->distinct()->pluck('user_id');
        $assignUserIds = EmergencyAssignment::query()->overlapping($startUtc, $endUtc)->where('is_archived', false)->distinct()->pluck('user_id');

        $ids = $entryUserIds->merge($shiftUserIds)->merge($assignUserIds)->filter()->unique()->values();

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Stabile Farbtonzuordnung pro Benutzer (HSL Hue 0–359). Spiegelt das Schema
     * wider, das auch die Wochenansicht für die Eintragsränder nutzt.
     */
    public function userHue(int $userId): int {
        return abs(crc32((string) $userId)) % 360;
    }

    /**
     * Group an item collection by ISO day-of-week index (0 = Monday … 6 = Sunday)
     * relative to the given week start.
     *
     * @template TItem of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TItem>  $items
     * @return array<int, Collection<int, TItem>>
     */
    public function groupByDay(Collection $items, CarbonImmutable $weekStart): array {
        $groups = [];
        for ($d = 0; $d < 7; $d++) {
            $groups[$d] = collect();
        }

        foreach ($items as $item) {
            /** @var Model&object{start_at: mixed, end_at: mixed} $item */
            /** @var CarbonInterface $itemStart */
            $itemStart = $item->start_at instanceof CarbonInterface
                ? $item->start_at
                : CarbonImmutable::parse((string) $item->start_at);

            $itemEnd = $item->end_at instanceof CarbonInterface
                ? $item->end_at
                : ($item->end_at !== null ? CarbonImmutable::parse((string) $item->end_at) : $itemStart);

            for ($d = 0; $d < 7; $d++) {
                $dayStart = $weekStart->addDays($d);
                $dayEnd = $dayStart->addDay();

                if ($itemStart->lessThan($dayEnd) && $itemEnd->greaterThan($dayStart)) {
                    $groups[$d]->push($item);
                }
            }
        }

        return $groups;
    }

    /**
     * Compute placement (top%, height%) of an item inside a single day cell.
     *
     * @return array{top: float, height: float}
     */
    public function placement(CarbonInterface $start, ?CarbonInterface $end, CarbonImmutable $day): array {
        $dayStart = $day->startOfDay();
        $dayEnd = $day->addDay()->startOfDay();

        $slotMinutes = (int) Setting::get('ui.calendar.slot_minutes', 30);
        $effectiveStart = $start->lessThan($dayStart) ? $dayStart : $start;
        $effectiveEnd = $end === null
            ? $effectiveStart->addMinutes($slotMinutes)
            : ($end->greaterThan($dayEnd) ? $dayEnd : $end);

        if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
            $effectiveEnd = $effectiveStart->addMinutes(15);
        }

        $minutesInDay = 24 * 60;
        $topMinutes = $effectiveStart->diffInMinutes($dayStart, true);
        $durationMinutes = $effectiveStart->diffInMinutes($effectiveEnd, true);

        return [
            'top' => round($topMinutes / $minutesInDay * 100, 3),
            'height' => max(round($durationMinutes / $minutesInDay * 100, 3), 1.0),
        ];
    }

    /**
     * Greedy lane packing für überlappende Tageseinträge.
     * Liefert für jeden Eintrag neben top/height auch left%/width% damit sich die Karten
     * nebeneinander anordnen statt sich zu überlappen.
     *
     * @param  Collection<int, DiaryEntry>  $entries
     * @return array<int, array{entry: DiaryEntry, top: float, height: float, left: float, width: float}>
     */
    public function layoutEntries(Collection $entries, CarbonImmutable $day): array {
        $items = [];
        foreach ($entries as $entry) {
            /** @var CarbonInterface $start */
            $start = $entry->start_at ?? CarbonImmutable::now();
            /** @var CarbonInterface|null $end */
            $end = $entry->end_at;

            $p = $this->placement($start, $end, $day);
            $items[] = [
                'entry' => $entry,
                'startMin' => $start->lessThan($day->startOfDay()) ? 0 : $start->diffInMinutes($day->startOfDay(), true),
                'endMin' => ($end ?? $start->addMinutes((int) Setting::get('ui.calendar.slot_minutes', 30)))->diffInMinutes($day->startOfDay(), true),
                'top' => $p['top'],
                'height' => $p['height'],
            ];
        }

        usort($items, fn($a, $b) => $a['startMin'] <=> $b['startMin'] ?: $b['endMin'] <=> $a['endMin']);

        // Cluster zusammenhängender Überlappungen, dann Lane innerhalb des Clusters
        $clusters = [];
        $current = [];
        $clusterEnd = -1.0;
        foreach ($items as $it) {
            if ($current === [] || $it['startMin'] < $clusterEnd) {
                $current[] = $it;
                $clusterEnd = max($clusterEnd, $it['endMin']);
            } else {
                $clusters[] = $current;
                $current = [$it];
                $clusterEnd = $it['endMin'];
            }
        }
        if ($current !== []) {
            $clusters[] = $current;
        }

        $laidOut = [];
        foreach ($clusters as $cluster) {
            $laneEnds = []; // index => endMin
            foreach ($cluster as &$it) {
                $assigned = null;
                foreach ($laneEnds as $i => $e) {
                    if ($e <= $it['startMin']) {
                        $assigned = $i;
                        break;
                    }
                }
                if ($assigned === null) {
                    $assigned = count($laneEnds);
                }
                $laneEnds[$assigned] = $it['endMin'];
                $it['lane'] = $assigned;
            }
            unset($it);
            $lanes = count($laneEnds);
            foreach ($cluster as $it) {
                $width = 100 / $lanes;
                $laidOut[] = [
                    'entry' => $it['entry'],
                    'top' => $it['top'],
                    'height' => $it['height'],
                    'left' => round($it['lane'] * $width, 3),
                    'width' => round($width, 3),
                ];
            }
        }

        return $laidOut;
    }
}
