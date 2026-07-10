<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericEventNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Notifications;

use App\Enums\Notification\NotificationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generische Benachrichtigung des NotificationDispatchers (MVP-018):
 * trägt Ereignistyp, Titel/Nachricht und Link; die Kanäle (database/mail)
 * werden vom Dispatcher pro Empfänger vorberechnet (Regel + Präferenzen).
 */
class GenericEventNotification extends Notification {
    use Queueable;

    /**
     * @param  array{title: string, message?: string|null, url?: string|null, icon?: string|null}  $payload
     * @param  list<string>  $channels  Laravel-Kanäle, z. B. ['database', 'mail']
     */
    public function __construct(
        public readonly NotificationEvent $event,
        public readonly array $payload,
        public readonly array $channels = ['database'],
        public readonly string $stage = 'initial',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array {
        unset($notifiable);

        return array_values(array_filter(
            $this->channels,
            static fn(string $c): bool => in_array($c, ['database', 'mail'], true),
        ));
    }

    public function toMail(object $notifiable): MailMessage {
        // Nutzertext gegen Markdown-Link-Injection entschärfen (s. MailText).
        $title = \App\Support\MailText::plain((string) $this->payload['title']);
        $message = \App\Support\MailText::plain((string) ($this->payload['message'] ?? ''));
        $url = $this->payload['url'] ?? null;

        $subjectKey = $this->stage === 'escalation' ? 'notification.mail.subject_escalation' : 'notification.mail.subject';
        $mail = (new MailMessage)
            ->subject(__($subjectKey, ['event' => $this->event->label(), 'title' => $title]))
            ->greeting(__('notification.mail.greeting', ['name' => $notifiable->name ?? '']))
            ->line($this->event->label() . ': ' . $title);

        if ($message !== '') {
            $mail->line($message);
        }
        if (is_string($url) && $url !== '') {
            $mail->action(__('notification.mail.action'), $url);
        }

        return $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        unset($notifiable);

        return [
            'event' => $this->event->value,
            'stage' => $this->stage,
            'title' => $this->payload['title'],
            'message' => $this->payload['message'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'icon' => $this->payload['icon'] ?? $this->event->icon(),
        ];
    }
}
