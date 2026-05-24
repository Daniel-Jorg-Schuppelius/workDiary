<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoomBookingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Event;

use App\Models\{Event, Room};
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\{Carbon, Collection};
use RuntimeException;

/**
 * Erkennt Doppelbelegungen von Räumen und bestätigt Buchungen.
 *
 * Konflikt-Modus wird über `config('events.room_conflict_mode')` gesteuert
 * (`hard` = throw, `warn` = nur Liste zurückgeben).
 */
class RoomBookingService {
    /**
     * Prüft Konflikte für eine geplante Belegung. Berücksichtigt
     * Setup-/Teardown-Puffer der bestehenden Buchungen.
     *
     * @return Collection<int, Event>  Konfliktierende Events (leer wenn frei).
     */
    public function findConflicts(
        Room $room,
        Carbon $startedAt,
        Carbon $endedAt,
        int $setupMinutesBefore = 0,
        int $teardownMinutesAfter = 0,
        ?int $ignoreEventId = null,
    ): Collection {
        $blockStart = $startedAt->copy()->subMinutes($setupMinutesBefore);
        $blockEnd = $endedAt->copy()->addMinutes($teardownMinutesAfter);

        // Grobe DB-Filter ±1 Tag um den Anfrage-Block, damit auch bestehende
        // Buchungen mit Setup-/Teardown-Puffer erfasst werden. Feinprüfung
        // (mit echten Puffern) erfolgt anschließend in PHP.
        $rangeStart = $blockStart->copy()->subDay();
        $rangeEnd = $blockEnd->copy()->addDay();

        return Event::query()
            ->with(['rooms' => fn($q) => $q->where('rooms.id', $room->getKey())])
            ->whereHas('rooms', function ($q) use ($room, $rangeStart, $rangeEnd): void {
                $q->where('rooms.id', $room->getKey())
                    ->where('event_room.started_at', '<', $rangeEnd)
                    ->where('event_room.ended_at', '>', $rangeStart);
            })
            ->when($ignoreEventId !== null, fn($q) => $q->where('id', '!=', $ignoreEventId))
            ->whereNull('cancelled_at')
            ->get()
            // Feinprüfung in PHP: setup_minutes_before / teardown_minutes_after
            // der bestehenden Buchung berücksichtigen.
            ->filter(function (Event $candidate) use ($room, $blockStart, $blockEnd): bool {
                $roomMatch = $candidate->rooms->firstWhere('id', $room->getKey());
                $pivot = $roomMatch?->getRelation('pivot');
                if (! $pivot instanceof Pivot) {
                    return false;
                }
                $start = Carbon::parse((string) $pivot->getAttribute('started_at'))
                    ->subMinutes((int) $pivot->getAttribute('setup_minutes_before'));
                $end = Carbon::parse((string) $pivot->getAttribute('ended_at'))
                    ->addMinutes((int) $pivot->getAttribute('teardown_minutes_after'));

                return $start->lt($blockEnd) && $end->gt($blockStart);
            })
            ->values();
    }

    /**
     * Hängt einen Raum an ein Event. Wirft bei Konflikt im `hard`-Modus.
     *
     * @throws RuntimeException
     */
    public function attach(
        Event $event,
        Room $room,
        Carbon $startedAt,
        Carbon $endedAt,
        int $setupMinutesBefore = 0,
        int $teardownMinutesAfter = 0,
    ): void {
        $conflicts = $this->findConflicts(
            $room,
            $startedAt,
            $endedAt,
            $setupMinutesBefore,
            $teardownMinutesAfter,
            $event->getKey(),
        );

        if ($conflicts->isNotEmpty() && config('events.room_conflict_mode', 'hard') === 'hard') {
            throw new RuntimeException(sprintf(
                'Raum "%s" ist im Zeitraum %s–%s bereits belegt (%d konkurrierende Event(s)).',
                $room->name,
                $startedAt->format('d.m.Y H:i'),
                $endedAt->format('d.m.Y H:i'),
                $conflicts->count(),
            ));
        }

        $event->rooms()->syncWithoutDetaching([
            $room->getKey() => [
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'setup_minutes_before' => $setupMinutesBefore,
                'teardown_minutes_after' => $teardownMinutesAfter,
            ],
        ]);
    }

    public function detach(Event $event, Room $room): void {
        $event->rooms()->detach($room->getKey());
    }

    /**
     * Liefert ein einfaches Belegungs-Raster für ein Datum.
     * Rückgabe: [ room_id => [['event' => Event, 'started_at' => Carbon, 'ended_at' => Carbon], ...] ]
     *
     * @return array<int, list<array{event: Event, started_at: Carbon, ended_at: Carbon}>>
     */
    public function gridForDay(Carbon $day): array {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();

        $events = Event::query()
            ->with('rooms')
            ->whereHas('rooms', function ($q) use ($from, $to): void {
                $q->where('event_room.started_at', '<', $to)
                    ->where('event_room.ended_at', '>', $from);
            })
            ->whereNull('cancelled_at')
            ->get();

        $grid = [];
        foreach ($events as $event) {
            foreach ($event->rooms as $room) {
                $pivot = $room->getRelation('pivot');
                if (! $pivot instanceof Pivot) {
                    continue;
                }
                $grid[$room->getKey()][] = [
                    'event' => $event,
                    'started_at' => Carbon::parse((string) $pivot->getAttribute('started_at')),
                    'ended_at' => Carbon::parse((string) $pivot->getAttribute('ended_at')),
                ];
            }
        }

        return $grid;
    }
}
