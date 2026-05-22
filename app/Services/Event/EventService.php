<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Event;

use App\Enums\Event\EventStatus;
use App\Enums\Event\ParticipantRole;
use App\Enums\Event\ParticipantStatus;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Schreibender API-Pfad rund um Event. Bündelt Transaktion + Raumbuchung
 * + Teilnehmer-Sync + Reminder-Scheduling + optionale Serien-Materialisierung,
 * damit Controller schlank bleiben.
 */
class EventService {
    public function __construct(
        private readonly RoomBookingService $rooms,
        private readonly ReminderService $reminders,
        private readonly RecurrenceService $recurrence,
    ) {
    }

    /**
     * @param array<string, mixed>                                              $data         Event-Felder (fillable).
     * @param array<int, array{room_id:int, started_at?:string|Carbon, ended_at?:string|Carbon, setup_minutes_before?:int, teardown_minutes_after?:int}> $rooms
     * @param array<int, array{user_id:int, role?:string, status?:string}>     $participants
     */
    public function create(array $data, array $rooms = [], array $participants = []): Event {
        return DB::transaction(function () use ($data, $rooms, $participants): Event {
            $event = Event::create($data);

            foreach ($rooms as $row) {
                /** @var Room $room */
                $room = Room::findOrFail($row['room_id']);
                $start = isset($row['started_at']) ? Carbon::parse($row['started_at']) : $event->started_at;
                $end = isset($row['ended_at']) ? Carbon::parse($row['ended_at']) : $event->ended_at;
                $this->rooms->attach(
                    $event,
                    $room,
                    $start,
                    $end,
                    (int) ($row['setup_minutes_before'] ?? 0),
                    (int) ($row['teardown_minutes_after'] ?? 0),
                );
            }

            $this->syncParticipants($event, $participants);

            if (! empty($event->recurrence_rule)) {
                $this->recurrence->materialize($event);
            }

            $this->reminders->scheduleFor($event);

            return $event->fresh(['rooms', 'participants', 'reminders']) ?? $event;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>>|null $rooms        null = Räume nicht antasten
     * @param array<int, array<string, mixed>>|null $participants null = Teilnehmer nicht antasten
     */
    public function update(Event $event, array $data, ?array $rooms = null, ?array $participants = null): Event {
        return DB::transaction(function () use ($event, $data, $rooms, $participants): Event {
            $event->update($data);

            if ($rooms !== null) {
                $event->rooms()->detach();
                foreach ($rooms as $row) {
                    /** @var Room $room */
                    $room = Room::findOrFail($row['room_id']);
                    $start = isset($row['started_at']) ? Carbon::parse($row['started_at']) : $event->started_at;
                    $end = isset($row['ended_at']) ? Carbon::parse($row['ended_at']) : $event->ended_at;
                    $this->rooms->attach(
                        $event,
                        $room,
                        $start,
                        $end,
                        (int) ($row['setup_minutes_before'] ?? 0),
                        (int) ($row['teardown_minutes_after'] ?? 0),
                    );
                }
            }

            if ($participants !== null) {
                /** @var array<int, array{user_id:int, role?:string, status?:string}> $participants */
                $this->syncParticipants($event, $participants);
            }

            $this->reminders->scheduleFor($event);

            return $event->fresh(['rooms', 'participants', 'reminders']) ?? $event;
        });
    }

    public function cancel(Event $event, ?string $reason = null): Event {
        $event->update([
            'status' => EventStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $event;
    }

    /**
     * @param array<int, array{user_id:int, role?:string, status?:string}> $participants
     */
    public function syncParticipants(Event $event, array $participants): void {
        $sync = [];
        foreach ($participants as $row) {
            $sync[(int) $row['user_id']] = [
                'role' => $row['role'] ?? ParticipantRole::Attendee->value,
                'status' => $row['status'] ?? ParticipantStatus::Invited->value,
            ];
        }
        $event->participants()->sync($sync);
    }

    public function addParticipant(
        Event $event,
        User $user,
        ParticipantRole $role = ParticipantRole::Attendee,
        ParticipantStatus $status = ParticipantStatus::Invited,
    ): void {
        $event->participants()->syncWithoutDetaching([
            $user->getKey() => [
                'role' => $role->value,
                'status' => $status->value,
            ],
        ]);
    }

    public function removeParticipant(Event $event, User $user): void {
        $event->participants()->detach($user->getKey());
    }
}
