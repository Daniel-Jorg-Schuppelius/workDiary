<?php

namespace App\Services\Calendar;

use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WeekViewService
{
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
    public function build(CarbonInterface $anchor, User $user, bool $teamScope, ?int $filterUserId = null): array
    {
        $start = CarbonImmutable::instance($anchor)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $end = $start->addDays(7); // exclusive

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = $start->addDays($i);
        }

        $shifts = OnCallShift::query()
            ->with('user:id,name')
            ->overlapping($start, $end)
            ->where('is_archived', false)
            ->when(! $teamScope, fn ($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn ($q) => $q->where('user_id', $filterUserId))
            ->orderBy('start_at')
            ->get();

        $assignments = EmergencyAssignment::query()
            ->with('user:id,name')
            ->overlapping($start, $end)
            ->where('is_archived', false)
            ->when(! $teamScope, fn ($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn ($q) => $q->where('user_id', $filterUserId))
            ->orderBy('start_at')
            ->get();

        $entries = DiaryEntry::query()
            ->with('user:id,name')
            ->where('is_archived', false)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNotNull('end_at')
                            ->where('start_at', '<', $end)
                            ->where('end_at', '>', $start);
                    });
            })
            ->when(! $teamScope, fn ($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn ($q) => $q->where('user_id', $filterUserId))
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
    public function usersInWeek(CarbonInterface $anchor): Collection
    {
        $start = CarbonImmutable::instance($anchor)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $end = $start->addDays(7);

        $entryUserIds = DiaryEntry::query()
            ->where('is_archived', false)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNotNull('end_at')
                            ->where('start_at', '<', $end)
                            ->where('end_at', '>', $start);
                    });
            })
            ->distinct()
            ->pluck('user_id');

        $shiftUserIds = OnCallShift::query()->overlapping($start, $end)->where('is_archived', false)->distinct()->pluck('user_id');
        $assignUserIds = EmergencyAssignment::query()->overlapping($start, $end)->where('is_archived', false)->distinct()->pluck('user_id');

        $ids = $entryUserIds->merge($shiftUserIds)->merge($assignUserIds)->filter()->unique()->values();

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Stabile Farbtonzuordnung pro Benutzer (HSL Hue 0–359). Spiegelt das Schema
     * wider, das auch die Wochenansicht für die Eintragsränder nutzt.
     */
    public function userHue(int $userId): int
    {
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
    public function groupByDay(Collection $items, CarbonImmutable $weekStart): array
    {
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
    public function placement(CarbonInterface $start, ?CarbonInterface $end, CarbonImmutable $day): array
    {
        $dayStart = $day->startOfDay();
        $dayEnd = $day->addDay()->startOfDay();

        $effectiveStart = $start->lessThan($dayStart) ? $dayStart : $start;
        $effectiveEnd = $end === null
            ? $effectiveStart->addMinutes(30)
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
    public function layoutEntries(Collection $entries, CarbonImmutable $day): array
    {
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
                'endMin' => ($end ?? $start->addMinutes(30))->diffInMinutes($day->startOfDay(), true),
                'top' => $p['top'],
                'height' => $p['height'],
            ];
        }

        usort($items, fn ($a, $b) => $a['startMin'] <=> $b['startMin'] ?: $b['endMin'] <=> $a['endMin']);

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
