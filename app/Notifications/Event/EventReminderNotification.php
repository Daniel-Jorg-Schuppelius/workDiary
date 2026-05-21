<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EventReminderNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications\Event;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification {
    use Queueable;

    /**
     * @param  list<string>  $channels  e.g. ['mail', 'database']
     */
    public function __construct(
        public readonly Event $event,
        public readonly array $channels = ['mail', 'database'],
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array {
        return array_values(array_filter($this->channels, function (string $c): bool {
            return in_array($c, ['mail', 'database'], true);
        }));
    }

    public function toMail(object $notifiable): MailMessage {
        $when = optional($this->event->started_at)->isoFormat('LLLL');

        return (new MailMessage)
            ->subject(__('Erinnerung: :title', ['title' => $this->event->title]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Folgende Veranstaltung steht an:'))
            ->line($this->event->title)
            ->line(__('Beginn: :when', ['when' => $when]))
            ->action(__('Veranstaltung öffnen'), route('events.show', $this->event));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        return [
            'event_id' => $this->event->getKey(),
            'title' => $this->event->title,
            'started_at' => optional($this->event->started_at)->toIso8601String(),
            'url' => route('events.show', $this->event),
            'icon' => 'event',
        ];
    }
}
