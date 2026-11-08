<?php
/*
 * Created on   : Tue Nov 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectTimeShortfallNotification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Notifications\Project;

use App\Notifications\DirectNotification;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Eine Meldung je Projekt und Tag an die Projektleitung (Feature 194,
 * MVP-1469): wie viele Personen unter ihrer Sollzeit lagen, mit Sprung auf die
 * Befunde am Zeitreiter.
 */
class ProjectTimeShortfallNotification extends DirectNotification {
    private const string TITLE_KEY = 'Unter Soll: :count Personen im Projekt :project am :date';

    private const string MESSAGE_KEY = 'Am :date lagen :count Personen im Projekt :project unter ihrer Sollzeit. Bitte die Befunde prüfen, bestätigen oder Nachbuchungen anstoßen.';

    public function __construct(
        public readonly string $projectName,
        public readonly string $date,
        public readonly int $count,
        public readonly string $url,
    ) {
        parent::__construct(['mail', 'database']);
    }

    /** @return array<string, string|int> */
    private function params(): array {
        return ['count' => $this->count, 'project' => $this->projectName, 'date' => $this->date];
    }

    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)
            ->subject(__(self::TITLE_KEY, $this->params()))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name ?? '']))
            ->line(__(self::MESSAGE_KEY, $this->params()))
            ->action(__('Befunde öffnen'), $this->url);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array {
        return [
            'title' => NotificationText::render(self::TITLE_KEY, $this->params()),
            'title_key' => self::TITLE_KEY,
            'title_params' => $this->params(),
            'message' => NotificationText::render(self::MESSAGE_KEY, $this->params()),
            'message_key' => self::MESSAGE_KEY,
            'message_params' => $this->params(),
            'count' => $this->count,
            'icon' => 'flag',
            'url' => $this->url,
        ];
    }
}
