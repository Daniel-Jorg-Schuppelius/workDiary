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
     * @param  array{title: string, title_key?: string|null, title_params?: array<string, mixed>|null, message?: string|null, message_key?: string|null, message_params?: array<string, mixed>|null, url?: string|null, icon?: string|null, due_at?: \DateTimeInterface|string|null}  $payload
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
        // Mit title_key wird der Titel hier — bereits in der Empfänger-Locale
        // (HasLocalePreference auf User) — frisch gerendert.
        $title = \App\Support\MailText::plain($this->title());
        $message = \App\Support\MailText::plain($this->message());
        $url = $this->payload['url'] ?? null;

        // Alle Eskalationsstufen (escalation/escalation2/escalation3, MVP-331)
        // tragen den Eskalations-Betreff.
        $subjectKey = str_starts_with($this->stage, 'escalation') ? 'notification.mail.subject_escalation' : 'notification.mail.subject';
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

    /** Titel in der aktuell aktiven Locale (title_key gewinnt über title). */
    public function title(): string {
        return \App\Support\NotificationText::title($this->payload);
    }

    /** Nachricht in der aktuell aktiven Locale (message_key gewinnt über message). */
    public function message(): string {
        return \App\Support\NotificationText::message($this->payload);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        unset($notifiable);

        return [
            'event' => $this->event->value,
            'stage' => $this->stage,
            'title' => $this->payload['title'],
            // Lang-Key + Rohparameter mitschreiben, damit die Anzeige in der
            // Sprache des Betrachters übersetzen kann (NotificationText).
            'title_key' => $this->payload['title_key'] ?? null,
            'title_params' => $this->payload['title_params'] ?? null,
            'message' => $this->payload['message'] ?? null,
            'message_key' => $this->payload['message_key'] ?? null,
            'message_params' => $this->payload['message_params'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'icon' => $this->payload['icon'] ?? $this->event->icon(),
        ];
    }
}
