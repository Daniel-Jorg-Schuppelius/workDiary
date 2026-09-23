<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventSeatService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Participation;

use App\Models\Calendar\Event;

/**
 * Kapazität und Warteliste eines Termins über alle Teilnehmerarten
 * (MVP-843, aus dem LMS-Dienst gelöst): ohne Obergrenze ist immer Platz,
 * sonst zählen Benutzer- und Mitgliedsteilnahmen gemeinsam. Ein frei
 * werdender Platz geht an den am längsten Wartenden — unabhängig von der
 * Teilnehmerart. Aufrufer sperren den Termin in ihrer Transaktion.
 */
class EventSeatService {
    /** @var list<SeatAdapter> */
    private readonly array $adapters;

    public function __construct(UserSeatAdapter $users, ClubMemberSeatAdapter $members) {
        $this->adapters = [$users, $members];
    }

    public function takenSeats(Event $event): int {
        $taken = 0;
        foreach ($this->adapters as $adapter) {
            $taken += $adapter->takenSeats($event);
        }

        return $taken;
    }

    /** Freie Plätze oder null ohne Obergrenze. */
    public function freeSeats(Event $event): ?int {
        return $event->max_participants === null ? null : max(0, $event->max_participants - $this->takenSeats($event));
    }

    public function hasFreeSeat(Event $event): bool {
        return $event->max_participants === null || $this->takenSeats($event) < $event->max_participants;
    }

    /** Lässt den am längsten Wartenden nachrücken, sofern ein Platz frei ist. */
    public function promoteNext(Event $event): ?WaitlistCandidate {
        if (! $this->hasFreeSeat($event)) {
            return null;
        }

        $best = null;
        foreach ($this->adapters as $adapter) {
            $candidate = $adapter->nextWaitlisted($event);
            if ($candidate !== null && ($best === null || $candidate->waitingSince->lessThan($best->waitingSince))) {
                $best = $candidate;
            }
        }

        $best?->promote();

        return $best;
    }
}
