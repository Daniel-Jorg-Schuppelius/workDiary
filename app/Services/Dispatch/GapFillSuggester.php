<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GapFillSuggester.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Dispatch;

use App\Enums\Diary\{DispatchStatus, Mode};
use App\Models\{AvailabilityWindow, DiaryEntry, DiaryEntryEvent, ScheduledShift, Tour, User};
use App\Services\Location\GeofenceMatcher;
use App\Services\Routing\{Coordinate, OsrmRouter};
use App\Services\Schedule\QualificationGate;
use Carbon\CarbonImmutable;

/**
 * Leerzeit-/Lückenfüller-Vorschläge (Epic 14.2, MVP-245): übersetzt
 * Schichten, Touren und bereits disponierte Aufträge eines Tages in freie
 * Slots und schlägt passende unverplante/flexible Aufträge vor —
 * Datumskorridor, Uhrzeitfenster, erwartete Dauer, Pflichtqualifikationen
 * und Mandantengrenze werden geprüft; Nähe über OSRM (zusätzliche
 * Fahrzeit) oder sichtbar gekennzeichnete Haversine-Schätzung.
 *
 * Bewusst KEINE Standortüberwachung: es zählen nur Auftrags-/Kunden-
 * Koordinaten (Planungsdaten), nie Live-Positionen (LocationVisit bleibt
 * unangetastet). Übernahme ist eine bewusste Disponenten-Aktion, die im
 * Auftragsverlauf (DiaryEntryEvent) nachvollziehbar bleibt.
 */
class GapFillSuggester {
    private const DEFAULT_DAY_START = '08:00';

    private const DEFAULT_DAY_END = '17:00';

    private const AVERAGE_SPEED_KMH = 40.0;

    public function __construct(
        private readonly QualificationGate $qualifications,
        private readonly DispatchConflictChecker $conflicts,
        private readonly OsrmRouter $router,
    ) {}

    /**
     * @return list<array{
     *   entry: DiaryEntry,
     *   slot: array{start: string, end: string, net_minutes: int},
     *   duration_minutes: int,
     *   distance_km: float|null,
     *   extra_travel_minutes: int|null,
     *   distance_is_estimate: bool,
     *   reasons: list<string>,
     *   warnings: list<string>,
     *   score: int
     * }>
     */
    public function suggestFor(User $user, CarbonImmutable $date, int $limit = 5): array {
        $slots = $this->freeSlots($user, $date);
        if ($slots === []) {
            return [];
        }

        $anchor = $this->anchorCoordinate($user, $date);
        $dismissed = $this->dismissedEntryIds($user, $date);

        $candidates = DiaryEntry::query()
            ->open()
            ->whereNull('tour_id')
            ->where(fn($q) => $q->whereNull('assigned_user_id')->orWhere(function ($flex): void {
                // flexible Modi dürfen umgeplant vorgeschlagen werden
                $flex->whereIn('mode', [Mode::Window->value, Mode::Backlog->value, Mode::Deadline->value]);
            }))
            ->where(fn($q) => $q->whereNull('dispatch_status')->orWhere('dispatch_status', DispatchStatus::Unplanned->value))
            ->with(['customer', 'requiredQualifications'])
            ->limit(200)
            ->get()
            ->filter(fn(DiaryEntry $entry): bool => ! in_array((int) $entry->id, $dismissed, true)
                && $this->withinDateCorridor($entry, $date));

        $suggestions = [];
        foreach ($candidates as $entry) {
            $duration = $this->expectedDuration($entry);
            $slot = $this->firstFittingSlot($slots, $entry, $duration);
            if ($slot === null) {
                continue;
            }

            // Pflichtqualifikationen des Auftrags (MVP-245-DoD).
            if ($entry->requiredQualifications->isNotEmpty()) {
                $status = $this->qualifications->statusFor($user, $entry->requiredQualifications, $date);
                if (in_array('missing', $status, true)) {
                    continue;
                }
            }

            [$distanceKm, $extraTravel, $isEstimate] = $this->proximity($anchor, $entry);

            $reasons = [
                (string) __('Freier Slot :from–:to (:net Min. netto)', ['from' => $slot['start'], 'to' => $slot['end'], 'net' => $slot['net_minutes']]),
                (string) __('Dauer ca. :min Min.', ['min' => $duration]),
                (string) __('Kunde/Objekt: :name', ['name' => $entry->customer->name ?? '—']),
            ];
            if ($distanceKm !== null) {
                $reasons[] = $isEstimate
                    ? (string) __('Entfernung ca. :km km (Luftlinie, grobe Schätzung)', ['km' => number_format($distanceKm, 1, ',', '.')])
                    : (string) __('Entfernung :km km, zusätzliche Fahrzeit ca. :min Min. (Route)', ['km' => number_format($distanceKm, 1, ',', '.'), 'min' => $extraTravel]);
            }
            if ($entry->time_window_start !== null || $entry->time_window_end !== null) {
                $reasons[] = (string) __('Zeitfenster des Auftrags: :from–:to', ['from' => $entry->time_window_start ?? '—', 'to' => $entry->time_window_end ?? '—']);
            }
            if ($entry->due_date !== null) {
                $reasons[] = (string) __('Frist: :date', ['date' => $entry->due_date->toDateString()]);
            }
            $reasons[] = (string) __('Priorität: :priority', ['priority' => $entry->priority->value ?? 'normal']);

            // Konfliktvorschau (Compliance/Doppelbelegung) als Warnungen.
            $warnings = [];
            $slotStart = $date->setTimeFromTimeString($slot['start']);
            $report = $this->conflicts->check($entry, (int) $user->id, $slotStart, $slotStart->addMinutes($duration));
            foreach ($report->violations as $violation) {
                $warnings[] = (string) $violation->message;
            }

            $score = 50;
            $score += match ($entry->priority->value ?? 'normal') {
                'urgent' => 30, 'high' => 20, 'normal' => 10, default => 0,
            };
            if ($entry->due_date !== null && $entry->due_date->lte($date->addDays(3))) {
                $score += 15; // Frist drängt
            }
            if ($distanceKm !== null) {
                $score += (int) max(0, 20 - $distanceKm); // Nähe belohnen
            }
            $score -= count($warnings) * 10;

            $suggestions[] = [
                'entry' => $entry,
                'slot' => $slot,
                'duration_minutes' => $duration,
                'distance_km' => $distanceKm,
                'extra_travel_minutes' => $extraTravel,
                'distance_is_estimate' => $isEstimate,
                'reasons' => $reasons,
                'warnings' => $warnings,
                'score' => $score,
            ];
        }

        usort($suggestions, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Freie Slots des Tages: Arbeitsfenster (Schichten, sonst Verfügbarkeit,
     * sonst Standard-Arbeitstag) minus belegte Auftrags-/Tourzeiten.
     *
     * @return list<array{start: string, end: string, net_minutes: int}>
     */
    public function freeSlots(User $user, CarbonImmutable $date): array {
        $windows = [];
        $shifts = ScheduledShift::query()
            ->forUser((int) $user->id)
            ->forDate($date)
            ->get();
        foreach ($shifts as $shift) {
            $start = $shift->resolvedStartTime();
            $end = $shift->resolvedEndTime();
            if ($start !== null && $end !== null) {
                $windows[] = [substr($start, 0, 5), substr($end, 0, 5)];
            }
        }
        if ($windows === []) {
            $availability = AvailabilityWindow::query()
                ->forUser((int) $user->id)
                ->forDate($date)
                ->get()
                ->filter(fn(AvailabilityWindow $window): bool => $window->kind->value !== 'unavailable');
            foreach ($availability as $window) {
                $windows[] = [
                    $window->start_time !== null ? substr((string) $window->start_time, 0, 5) : self::DEFAULT_DAY_START,
                    $window->end_time !== null ? substr((string) $window->end_time, 0, 5) : self::DEFAULT_DAY_END,
                ];
            }
        }
        if ($windows === []) {
            $windows[] = [self::DEFAULT_DAY_START, self::DEFAULT_DAY_END];
        }

        // Belegte Intervalle: bereits disponierte Aufträge des Tages.
        $busy = [];
        $assigned = DiaryEntry::query()
            ->where('assigned_user_id', $user->id)
            ->where(fn($q) => $q
                // disponiert ohne konkreten start_at (Dispo-Normalfall)
                ->whereDate('scheduled_for', $date->toDateString())
                ->orWhere(fn($w) => $w->overlappingDateRange($date->toDateString(), $date->toDateString())))
            ->get();
        foreach ($assigned as $entry) {
            $interval = $this->busyInterval($entry, $date);
            if ($interval !== null) {
                $busy[] = $interval;
            }
        }
        // Tour-Zeitrahmen des Tages zählt als belegt (geplante Fahrleistung).
        $tour = Tour::query()->forUser((int) $user->id)->onDate($date->toDateString())->first();
        if ($tour !== null && $tour->planned_duration_minutes > 0) {
            // Tourdauer blockt vom Fensterbeginn an (grobe Planungssicht).
            $tourStart = $windows[0][0];
            $busy[] = [$tourStart, CarbonImmutable::parse($date->toDateString() . ' ' . $tourStart)->addMinutes((int) $tour->planned_duration_minutes)->format('H:i')];
        }

        return $this->subtract($windows, $busy);
    }

    /** Übernahme (bewusste Disponenten-Aktion): plant + protokolliert. */
    public function apply(DiaryEntry $entry, User $dispatcher, User $assignee, CarbonImmutable $date, string $startTime, int $durationMinutes): void {
        $entry->forceFill([
            'assigned_user_id' => $assignee->id,
            'scheduled_for' => $date->toDateString(),
            'time_window_start' => $startTime,
            'time_window_end' => CarbonImmutable::parse($date->toDateString() . ' ' . $startTime)->addMinutes($durationMinutes)->format('H:i'),
            'planned_at' => now(),
            'planned_by_user_id' => $dispatcher->id,
            'planned_minutes' => $durationMinutes,
        ])->save();

        app(DispatchStatusResolver::class)->transition($entry, DispatchStatus::Planned);

        DiaryEntryEvent::query()->create([
            'organization_id' => $entry->organization_id,
            'diary_entry_id' => $entry->id,
            'event' => 'dispatch.gap_fill_applied',
            'from_status' => $entry->status->slug(),
            'to_status' => $entry->status->slug(),
            'actor_user_id' => $dispatcher->id,
            'actor_kind' => 'user',
            'note' => (string) __('Leerzeit-Vorschlag übernommen: :date :time (:user)', ['date' => $date->toDateString(), 'time' => $startTime, 'user' => $assignee->name]),
            'payload' => ['assignee_id' => $assignee->id, 'date' => $date->toDateString(), 'start' => $startTime, 'duration_minutes' => $durationMinutes],
            'occurred_at' => now(),
        ]);
    }

    /** Ablehnung: protokolliert + unterdrückt den Vorschlag für User+Tag. */
    public function dismiss(DiaryEntry $entry, User $dispatcher, User $assignee, CarbonImmutable $date, ?string $reason = null): void {
        DiaryEntryEvent::query()->create([
            'organization_id' => $entry->organization_id,
            'diary_entry_id' => $entry->id,
            'event' => 'dispatch.gap_fill_dismissed',
            'from_status' => $entry->status->slug(),
            'to_status' => $entry->status->slug(),
            'actor_user_id' => $dispatcher->id,
            'actor_kind' => 'user',
            'note' => $reason,
            'payload' => ['assignee_id' => $assignee->id, 'date' => $date->toDateString()],
            'occurred_at' => now(),
        ]);
    }

    /** @return array<int, int> */
    private function dismissedEntryIds(User $user, CarbonImmutable $date): array {
        return DiaryEntryEvent::query()
            ->where('event', 'dispatch.gap_fill_dismissed')
            ->where('payload->assignee_id', $user->id)
            ->where('payload->date', $date->toDateString())
            ->pluck('diary_entry_id')
            ->map(fn($id): int => (int) $id)
            ->all();
    }

    private function withinDateCorridor(DiaryEntry $entry, CarbonImmutable $date): bool {
        $day = $date->toDateString();

        return match ($entry->mode) {
            Mode::Backlog => true,
            Mode::Deadline => $entry->due_date === null || $entry->due_date->toDateString() >= $day,
            Mode::Window => ($entry->window_start_date === null || $entry->window_start_date->toDateString() <= $day)
                && ($entry->window_end_date === null || $entry->window_end_date->toDateString() >= $day),
            default => $entry->scheduled_for !== null && $entry->scheduled_for->toDateString() === $day,
        };
    }

    private function expectedDuration(DiaryEntry $entry): int {
        $minutes = (int) ($entry->planned_minutes ?? 0);
        if ($minutes <= 0) {
            $minutes = (int) ($entry->service_minutes ?? 0);
        }

        return $minutes > 0 ? $minutes : 60;
    }

    /**
     * @param list<array{start: string, end: string, net_minutes: int}> $slots
     * @return array{start: string, end: string, net_minutes: int}|null
     */
    private function firstFittingSlot(array $slots, DiaryEntry $entry, int $duration): ?array {
        foreach ($slots as $slot) {
            if ($slot['net_minutes'] < $duration) {
                continue;
            }
            // Uhrzeitfenster des Auftrags muss den Slot schneiden.
            if ($entry->time_window_start !== null && substr((string) $entry->time_window_start, 0, 5) > $slot['end']) {
                continue;
            }
            if ($entry->time_window_end !== null && substr((string) $entry->time_window_end, 0, 5) < $slot['start']) {
                continue;
            }

            return $slot;
        }

        return null;
    }

    /** @return array{0: float|null, 1: int|null, 2: bool} [km, Zusatzminuten, Schätzung?] */
    private function proximity(?Coordinate $anchor, DiaryEntry $entry): array {
        $lat = $entry->address_lat ?? $entry->customer?->address_lat;
        $lng = $entry->address_lng ?? $entry->customer?->address_lng;
        if ($anchor === null || $lat === null || $lng === null) {
            return [null, null, true];
        }

        $meters = GeofenceMatcher::distanceMeters((float) $anchor->lat, (float) $anchor->lng, (float) $lat, (float) $lng);
        $km = round($meters / 1000, 1);

        // OSRM, wenn erreichbar: echte zusätzliche Fahrzeit hin+zurück zum Anker.
        try {
            $matrix = $this->router->table([
                [(float) $anchor->lat, (float) $anchor->lng],
                [(float) $lat, (float) $lng],
            ]);
            $seconds = (float) ($matrix[0][1] ?? 0) + (float) ($matrix[1][0] ?? 0);
            if ($seconds > 0) {
                return [$km, (int) round($seconds / 60), false];
            }
        } catch (\Throwable) {
            // Offline/kein OSRM → Haversine-Schätzung, sichtbar gekennzeichnet.
        }

        $estimateMinutes = (int) round($km / self::AVERAGE_SPEED_KMH * 60 * 2);

        return [$km, $estimateMinutes > 0 ? $estimateMinutes : null, true];
    }

    private function anchorCoordinate(User $user, CarbonImmutable $date): ?Coordinate {
        // Anker = letzter Stop der Tages-Tour, sonst letzter disponierten
        // Auftrag mit Koordinaten (reine PLANUNGS-Daten, keine Ortung).
        $tour = Tour::query()->forUser((int) $user->id)->onDate($date->toDateString())->first();
        if ($tour !== null) {
            $stop = $tour->diaryEntries()
                ->whereNotNull('address_lat')
                ->whereNotNull('address_lng')
                ->orderByRaw('tour_position IS NULL DESC')
                ->orderByDesc('tour_position')
                ->orderByDesc('id')
                ->first();
            if ($stop !== null) {
                return new Coordinate((float) $stop->address_lat, (float) $stop->address_lng);
            }
            if ($tour->start_lat !== null && $tour->start_lng !== null) {
                return new Coordinate((float) $tour->start_lat, (float) $tour->start_lng);
            }
        }

        $last = DiaryEntry::query()
            ->where('assigned_user_id', $user->id)
            ->overlappingDateRange($date->toDateString(), $date->toDateString())
            ->whereNotNull('address_lat')
            ->whereNotNull('address_lng')
            ->orderByDesc('id')
            ->first();
        if ($last !== null) {
            return new Coordinate((float) $last->address_lat, (float) $last->address_lng);
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null */
    private function busyInterval(DiaryEntry $entry, CarbonImmutable $date): ?array {
        if ($entry->start_at !== null && $entry->start_at->isSameDay($date)) {
            $end = $entry->end_at ?? $entry->start_at->copy()->addMinutes($this->expectedDuration($entry));

            return [$entry->start_at->format('H:i'), $end->format('H:i')];
        }
        if ($entry->scheduled_for !== null && $entry->scheduled_for->toDateString() === $date->toDateString() && $entry->time_window_start !== null) {
            $start = substr((string) $entry->time_window_start, 0, 5);
            $end = $entry->time_window_end !== null
                ? substr((string) $entry->time_window_end, 0, 5)
                : CarbonImmutable::parse($date->toDateString() . ' ' . $start)->addMinutes($this->expectedDuration($entry))->format('H:i');

            return [$start, $end];
        }

        return null;
    }

    /**
     * Fenster minus Belegung → freie Slots mit Nettominuten.
     *
     * @param list<array{0: string, 1: string}> $windows
     * @param list<array{0: string, 1: string}> $busy
     * @return list<array{start: string, end: string, net_minutes: int}>
     */
    private function subtract(array $windows, array $busy): array {
        usort($busy, static fn(array $a, array $b): int => strcmp($a[0], $b[0]));
        $slots = [];
        foreach ($windows as [$windowStart, $windowEnd]) {
            $cursor = $windowStart;
            foreach ($busy as [$busyStart, $busyEnd]) {
                if ($busyEnd <= $cursor || $busyStart >= $windowEnd) {
                    continue;
                }
                if ($busyStart > $cursor) {
                    $slots[] = [$cursor, min($busyStart, $windowEnd)];
                }
                $cursor = max($cursor, min($busyEnd, $windowEnd));
            }
            if ($cursor < $windowEnd) {
                $slots[] = [$cursor, $windowEnd];
            }
        }

        $result = [];
        foreach ($slots as [$start, $end]) {
            $net = (int) (CarbonImmutable::parse('2000-01-01 ' . $end)->diffInMinutes(CarbonImmutable::parse('2000-01-01 ' . $start), true));
            if ($net >= 15) { // Mini-Lücken ignorieren
                $result[] = ['start' => $start, 'end' => $end, 'net_minutes' => $net];
            }
        }

        return $result;
    }
}
