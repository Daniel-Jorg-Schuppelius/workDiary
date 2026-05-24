<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReminderService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Event;

use App\Enums\Event\ParticipantStatus;
use App\Models\{Event, EventReminder, User};
use App\Notifications\Event\EventReminderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Erzeugt EventReminder-Datensätze aus den effektiven Offsets eines Events
 * (Override > Kategorie > Config-Default) und versendet fällige Reminder.
 */
class ReminderService {
    /**
     * @return array<int, int>  Effektive Offsets in Minuten vor Event-Start.
     */
    public function effectiveOffsets(Event $event): array {
        $overrides = $event->reminder_overrides;
        if (is_array($overrides) && $overrides !== []) {
            return array_values(array_map('intval', $overrides));
        }

        if ($event->category && is_array($event->category->reminder_offsets) && $event->category->reminder_offsets !== []) {
            return array_values(array_map('intval', $event->category->reminder_offsets));
        }

        /** @var array<int, int> $default */
        $default = (array) config('events.reminder_offsets', [10080, 1440, 60]);

        return array_values(array_map('intval', $default));
    }

    /**
     * @return array<int, string> Channel-Strings (mail | webpush | database).
     */
    public function effectiveChannels(): array {
        /** @var array<int, string> $channels */
        $channels = (array) config('events.channels', ['mail', 'database']);

        return array_values($channels);
    }

    /**
     * Stellt sicher, dass für ein Event die geplanten Reminder
     * (sent_at IS NULL) in der DB liegen — synchronisiert idempotent
     * gegen vorhandene, noch nicht versendete Reminder.
     *
     * @return int  Anzahl neu angelegter Reminder.
     */
    public function scheduleFor(Event $event): int {
        $offsets = $this->effectiveOffsets($event);
        $channels = $this->effectiveChannels();
        $created = 0;

        // Alte noch nicht versendete Reminder löschen — die Offsets oder
        // Channels könnten sich geändert haben.
        EventReminder::query()
            ->where('event_id', $event->getKey())
            ->whereNull('sent_at')
            ->delete();

        foreach ($offsets as $offset) {
            $remindAt = $event->started_at->copy()->subMinutes($offset);
            if ($remindAt->isPast()) {
                continue;
            }

            foreach ($channels as $channel) {
                EventReminder::create([
                    'event_id' => $event->getKey(),
                    'user_id' => null, // an alle Teilnehmer
                    'remind_at' => $remindAt,
                    'channel' => $channel,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Versendet alle fälligen Reminder.
     *
     * @return int Anzahl versendeter Reminder.
     */
    public function dispatchDue(?Carbon $now = null): int {
        $now ??= now();
        $sent = 0;

        EventReminder::query()
            ->with('event.participants')
            ->due($now)
            ->chunkById(100, function ($reminders) use (&$sent): void {
                /** @var EventReminder $reminder */
                foreach ($reminders as $reminder) {
                    try {
                        $this->dispatchOne($reminder);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error('Event reminder dispatch failed', [
                            'reminder_id' => $reminder->id,
                            'error' => $e->getMessage(),
                        ]);
                        $reminder->forceFill([
                            'error' => $e->getMessage(),
                        ])->save();
                    }
                }
            });

        return $sent;
    }

    private function dispatchOne(EventReminder $reminder): void {
        $event = $reminder->event;
        if (! $event || $event->isCancelled()) {
            $reminder->forceFill(['sent_at' => now(), 'error' => 'event cancelled'])->save();

            return;
        }

        $recipients = $this->resolveRecipients($reminder);
        if ($recipients->isEmpty()) {
            $reminder->forceFill(['sent_at' => now()])->save();

            return;
        }

        $channels = [$reminder->channel->value];

        $notification = new EventReminderNotification($event, $channels);
        foreach ($recipients as $user) {
            $user->notify($notification);
        }

        $reminder->forceFill(['sent_at' => now(), 'error' => null])->save();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function resolveRecipients(EventReminder $reminder): \Illuminate\Support\Collection {
        if ($reminder->user_id !== null) {
            $user = User::find($reminder->user_id);

            return collect($user ? [$user] : []);
        }

        $event = $reminder->event;
        if ($event === null) {
            return collect();
        }

        return $event->participants()
            ->wherePivotIn('status', [
                ParticipantStatus::Invited->value,
                ParticipantStatus::Accepted->value,
            ])
            ->get();
    }
}
