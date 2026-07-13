<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendarEventPublishJob.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Jobs\Notification;

use App\Models\Organization;
use App\Plugins\Contracts\{CalendarPublisher, PluginCapability};
use App\Plugins\PluginManager;
use App\Plugins\Support\Calendar\RemoteCalendarEvent;
use DateInterval;
use DateTimeZone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\{Carbon, Str};

/**
 * Publiziert eine terminartige Benachrichtigung als Kalendereintrag
 * (Kalender-Kanal, MVP-331, Bauturbo A11) über die A8-Publish-Infrastruktur:
 * alle aktivierten Plugins mit {@see PluginCapability::CalendarPublish}
 * (CalDAV/Microsoft 365/Google) erhalten das Element über
 * {@see CalendarPublisher::publishCalendarItem()}.
 *
 * Idempotent: die UID ist stabil pro Subjekt ({@see uidFor()}), der Publish-
 * Zustand liegt in ExternalReference (Hash-Vergleich) — erneutes Feuern
 * aktualisiert den Eintrag statt ihn zu duplizieren; ohne Kalender-Verbindung
 * passiert still nichts. Plugin-Fehler sind über {@see PluginManager::invoke()}
 * isoliert (Aufzeichnung statt Job-Fehlschlag), Transportfehler zählen in den
 * Publish-Services auf die einheitliche Verbindungs-Gesundheit ein.
 */
class CalendarEventPublishJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $organizationId,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $title,
        public readonly ?string $message,
        public readonly string $dueAtIso,
    ) {}

    /**
     * Stabile Kalender-UID einer Benachrichtigung: pro fachlichem Subjekt —
     * dueSoon/overdue desselben Subjekts meinen denselben Fristen-Eintrag
     * (Update statt zweiter Termin). Muster analog IcsFeedService::eventUid().
     */
    public static function uidFor(string $subjectType, int $subjectId): string {
        return 'notify-' . Str::slug(str_replace('\\', '-', $subjectType)) . '-' . $subjectId . '@workdiary';
    }

    public function handle(PluginManager $plugins): void {
        $organization = Organization::query()->find($this->organizationId);
        if (! $organization instanceof Organization) {
            return;
        }

        // Zeiten in der lokalen App-Zeitzone — wie die Termin-Publishes (A8).
        $tz = new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));
        $start = Carbon::parse($this->dueAtIso)->setTimezone($tz)->toDateTimeImmutable();

        $item = new RemoteCalendarEvent(
            uid: self::uidFor($this->subjectType, $this->subjectId),
            title: $this->title,
            description: $this->message,
            location: '',
            start: $start,
            end: $start->add(new DateInterval('PT1H')),
            timezone: $tz->getName(),
            referenceableType: $this->subjectType,
            referenceableId: $this->subjectId,
        );

        // Org-Kontext binden: per-Org-Plugin-Schalter/Auto-Disable und die
        // Verbindungs-Abfragen der Plugins brauchen die Ziel-Organisation.
        // Vorherige Bindung sichern/wiederherstellen (der sync-Driver läuft
        // INNERHALB eines Requests — Request-Kontext nicht wegwerfen).
        $previous = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        app()->instance('currentOrganization', $organization);

        try {
            foreach ($plugins->withCapability(PluginCapability::CalendarPublish) as $plugin) {
                if (! $plugin instanceof CalendarPublisher) {
                    continue;
                }
                $plugins->invoke($plugin, fn(): array => $plugin->publishCalendarItem($organization, $item));
            }
        } finally {
            if ($previous instanceof Organization) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }
}
