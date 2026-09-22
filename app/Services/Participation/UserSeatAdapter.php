<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserSeatAdapter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Participation;

use App\Enums\Event\ParticipantStatus;
use App\Models\{Event, EventParticipant};
use Illuminate\Support\Carbon;

/**
 * Benutzer-Teilnahmen über das Pivot `event_user` (Feature 028/149):
 * angenommen und anwesend belegen, wartend rückt nach Anmeldezeit nach.
 */
class UserSeatAdapter implements SeatAdapter {
    public function takenSeats(Event $event): int {
        return EventParticipant::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [ParticipantStatus::Accepted->value, ParticipantStatus::Attended->value])
            ->count();
    }

    public function nextWaitlisted(Event $event): ?WaitlistCandidate {
        /** @var EventParticipant|null $next */
        $next = EventParticipant::query()
            ->where('event_id', $event->id)
            ->where('status', ParticipantStatus::Waitlisted->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();
        if ($next === null) {
            return null;
        }

        return new WaitlistCandidate(
            $next->created_at ?? Carbon::now(),
            $next,
            static function () use ($next): EventParticipant {
                $next->update(['status' => ParticipantStatus::Accepted->value]);

                return $next->refresh();
            },
        );
    }
}
