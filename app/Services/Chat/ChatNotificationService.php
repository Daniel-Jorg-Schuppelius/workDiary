<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChatNotificationService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Chat;

use App\Enums\Notification\NotificationEvent;
use App\Models\Chat\{Message, Reminder};
use App\Models\User;
use App\Services\Notification\NotificationDispatcher;
use App\Support\Setting;

/**
 * Chat-Benachrichtigungen über den zentralen Dispatcher (Vollscan 2026-08-23,
 * B7 — löst den Legacy-PushNotifier ab). Die Empfängerermittlung ist Chat-
 * Fachlogik (Direktnachricht/@-Erwähnung/muted); Kanäle, Präferenzen und
 * Ruhezeiten regelt der Dispatcher je Empfänger.
 */
class ChatNotificationService {
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * Direktnachricht: alle anderen Mitglieder; Kanal/Gruppe: nur per @Name
     * erwähnte Mitglieder — stummgeschaltete nie.
     */
    public function messageCreated(Message $message): void {
        if (! $message->user_id) {
            return;
        }
        $message->loadMissing(['channel.members', 'user']);
        $channel = $message->channel;
        if ($channel === null) {
            return;
        }

        $body = (string) $message->body;
        // Stummgeschaltete per Pivot-Query ausschließen (kein Pivot-Property-Zugriff).
        $mutedIds = $channel->members()->wherePivotNotNull('muted_at')->pluck('users.id')->all();

        $recipients = $channel->members->filter(function (User $m) use ($message, $channel, $body, $mutedIds): bool {
            if ($m->id === $message->user_id || in_array($m->id, $mutedIds, true)) {
                return false;
            }

            return $channel->isDirect() || mb_stripos($body, '@' . $m->name) !== false;
        });
        if ($recipients->isEmpty()) {
            return;
        }

        $sender = $message->user->name ?? (string) __('notification.message.unknown_actor');
        $payload = [
            // Absender/Kanalname sind Rohdaten — nichts zu übersetzen.
            'title' => $channel->isDirect() ? $sender : '#' . ($channel->name ?? 'Chat') . ' · ' . $sender,
            'url' => route('chat.show', $channel),
            ...$this->bodyPayload($body),
        ];

        foreach ($recipients as $member) {
            $this->dispatcher->notify(NotificationEvent::ChatMessage, $message, $member, $payload);
        }
    }

    /** Fällige Chat-Erinnerung an den Ersteller der Erinnerung. */
    public function reminderDue(Reminder $reminder): void {
        $reminder->loadMissing(['user', 'message', 'channel']);
        $user = $reminder->user;
        $message = $reminder->message;
        $channel = $reminder->channel;
        if ($user === null || $message === null || $channel === null) {
            return;
        }

        $params = ['channel' => (string) ($channel->name ?? 'Chat')];
        $this->dispatcher->notify(NotificationEvent::ChatReminder, $reminder, $user, [
            'title' => (string) __('notification.message.chat_reminder_title', $params),
            'title_key' => 'notification.message.chat_reminder_title',
            'title_params' => $params,
            'url' => route('chat.show', $channel),
            ...$this->bodyPayload(trim((string) $message->body)),
        ]);
    }

    /**
     * Nachrichtentext als Payload-Message: Nutzertext roh (nicht übersetzbar),
     * leerer Text render-time als „[Anhang]" in der Empfängersprache.
     *
     * @return array{message: string, message_key?: string, message_params?: array<string, mixed>}
     */
    private function bodyPayload(string $body): array {
        if ($body === '') {
            return [
                'message' => (string) __('notification.message.chat_attachment'),
                'message_key' => 'notification.message.chat_attachment',
                'message_params' => [],
            ];
        }

        return ['message' => mb_substr($body, 0, (int) Setting::get('notifications.push.body_truncate', 120))];
    }
}
