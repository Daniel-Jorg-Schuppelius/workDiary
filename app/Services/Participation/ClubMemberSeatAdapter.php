<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberSeatAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Participation;

use App\Enums\Club\ClubParticipationStatus;
use App\Models\Club\ClubEventParticipation;
use App\Models\Event;
use Illuminate\Support\Carbon;

/**
 * Mitgliedsteilnahmen am Vereinstermin (MVP-843): angemeldet belegt,
 * Warteliste rückt in Anmeldereihenfolge nach.
 */
class ClubMemberSeatAdapter implements SeatAdapter {
    public function takenSeats(Event $event): int {
        return ClubEventParticipation::query()
            ->where('event_id', $event->id)
            ->where('status', ClubParticipationStatus::Registered->value)
            ->count();
    }

    public function nextWaitlisted(Event $event): ?WaitlistCandidate {
        /** @var ClubEventParticipation|null $next */
        $next = ClubEventParticipation::query()
            ->where('event_id', $event->id)
            ->where('status', ClubParticipationStatus::Waitlisted->value)
            ->orderBy('registered_at')
            ->orderBy('id')
            ->first();
        if ($next === null) {
            return null;
        }

        return new WaitlistCandidate(
            $next->registered_at ?? $next->created_at ?? Carbon::now(),
            $next,
            static function () use ($next): ClubEventParticipation {
                $next->update([
                    'status' => ClubParticipationStatus::Registered->value,
                    'promoted_at' => now(),
                ]);
                $next->audit('club.event.promoted');

                return $next->refresh();
            },
        );
    }
}
