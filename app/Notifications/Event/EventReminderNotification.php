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
use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\MailMessage;

class EventReminderNotification extends DirectNotification {
    private const TITLE_KEY = 'Erinnerung: :title';

    private const MESSAGE_KEY = 'Beginn: :when';

    /**
     * @param  list<string>  $channels  e.g. ['mail', 'database']
     */
    public function __construct(
        public readonly Event $event,
        array $channels = ['mail', 'database'],
    ) {
        parent::__construct($channels);
    }

    public function toMail(object $notifiable): MailMessage {
        $when = optional($this->event->started_at)->isoFormat('LLLL');

        return (new MailMessage)
            ->subject(__('Erinnerung: :title', ['title' => $this->event->title]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__('Folgende Veranstaltung steht an:'))
            ->line(\App\Support\MailText::plain($this->event->title))
            ->line(__('Beginn: :when', ['when' => $when]))
            ->action(__('Veranstaltung öffnen'), route('events.show', $this->event));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        $titleParams = ['title' => (string) $this->event->title];
        $startedAt = optional($this->event->started_at)->toIso8601String();

        $data = [
            'event_id' => $this->event->getKey(),
            'title' => NotificationText::render(self::TITLE_KEY, $titleParams),
            'title_key' => self::TITLE_KEY,
            'title_params' => $titleParams,
            'started_at' => $startedAt,
            'url' => route('events.show', $this->event),
            'icon' => 'event',
        ];

        if ($startedAt !== null) {
            // ISO-Zeitpunkt → NotificationText rendert in Anzeige-TZ/-Locale.
            $messageParams = ['when' => $startedAt];
            $data['message'] = NotificationText::render(self::MESSAGE_KEY, $messageParams);
            $data['message_key'] = self::MESSAGE_KEY;
            $data['message_params'] = $messageParams;
        }

        return $data;
    }
}
