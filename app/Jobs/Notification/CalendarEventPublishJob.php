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

use App\Jobs\Concerns\RetriesTransientFailures;
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
 * (Kalender-Kanal, MVP-331, Bauturbo A11): alle aktivierten Plugins mit
 * {@see PluginCapability::CalendarPublish} erhalten das Element über
 * {@see CalendarPublisher::publishCalendarItem()}.
 * Idempotent über die stabile UID ({@see uidFor()}); Plugin-Fehler sind über
 * {@see PluginManager::invoke()} isoliert (Aufzeichnung statt Job-Fehlschlag).
 */
class CalendarEventPublishJob implements ShouldQueue {
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RetriesTransientFailures;
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
     * Stabile Kalender-UID pro Subjekt: dueSoon/overdue desselben Subjekts meinen
     * denselben Eintrag (Update statt Duplikat). Muster analog IcsFeedService::eventUid().
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

        // Org-Kontext binden (Plugins brauchen die Ziel-Org) mit garantiertem
        // Restore — zentral über OrganizationContext (Vollaudit 2026-07, M42);
        // wichtig für den sync-Driver, der im Request läuft.
        \App\Support\OrganizationContext::run($organization, function () use ($plugins, $organization, $item): void {
            foreach ($plugins->withCapability(PluginCapability::CalendarPublish) as $plugin) {
                if (! $plugin instanceof CalendarPublisher) {
                    continue;
                }
                $plugins->invoke($plugin, fn(): array => $plugin->publishCalendarItem($organization, $item));
            }
        });
    }
}
