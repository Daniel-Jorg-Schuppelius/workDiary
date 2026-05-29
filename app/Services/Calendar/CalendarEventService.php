<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarEventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Calendar;

use App\Models\{DiaryEntry, EmergencyAssignment, OnCallShift, User};
use Carbon\CarbonInterface;

class CalendarEventService {
    /**
     * Build FullCalendar event payload for the given range.
     *
     * @return array<int, array{id: string, title: string, start: string, end: string|null, color: string, url: string|null}>
     */
    public function events(
        CarbonInterface $start,
        CarbonInterface $end,
        User $user,
        bool $teamScope = false,
        ?int $filterUserId = null,
    ): array {
        $events = [];

        $shifts = OnCallShift::query()
            ->with('user:id,name')
            ->overlapping($start, $end)
            ->where('is_archived', false)
            ->when(! $teamScope, fn($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn($q) => $q->where('user_id', $filterUserId))
            ->get();
        foreach ($shifts as $shift) {
            $events[] = [
                'id' => 'shift-' . $shift->id,
                'title' => __('Bereitschaft') . ' · ' . ($shift->user->name ?? '—'),
                'start' => $shift->start_at->toIso8601String(),
                'end' => $shift->end_at->toIso8601String(),
                'color' => '#0ea5e9',
                'url' => null,
            ];
        }

        $assignments = EmergencyAssignment::query()
            ->with('user:id,name')
            ->overlapping($start, $end)
            ->where('is_archived', false)
            ->when(! $teamScope, fn($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn($q) => $q->where('user_id', $filterUserId))
            ->get();
        foreach ($assignments as $assignment) {
            $events[] = [
                'id' => 'emergency-' . $assignment->id,
                'title' => __('Notdienst') . ' · ' . ($assignment->user->name ?? '—'),
                'start' => $assignment->start_at->toIso8601String(),
                'end' => $assignment->end_at->toIso8601String(),
                'color' => '#dc2626',
                'url' => null,
            ];
        }

        $entries = DiaryEntry::query()
            ->with('user:id,name')
            ->where('is_archived', false)
            ->whereNotNull('start_at')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNotNull('end_at')
                            ->where('start_at', '<', $end)
                            ->where('end_at', '>', $start);
                    });
            })
            ->when(! $teamScope, fn($q) => $q->where('user_id', $user->id))
            ->when($teamScope && $filterUserId, fn($q) => $q->where('user_id', $filterUserId))
            ->get();
        foreach ($entries as $entry) {
            $events[] = [
                'id' => 'entry-' . $entry->id,
                'title' => $entry->title ?: __('Auftragsbucheintrag'),
                'start' => $entry->start_at?->toIso8601String() ?? '',
                'end' => $entry->end_at?->toIso8601String(),
                'color' => '#16a34a',
                'url' => route('diary.show', $entry),
            ];
        }

        return $events;
    }
}
