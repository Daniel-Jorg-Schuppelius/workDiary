<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalDavPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CalDav;

use App\Models\{CalDavConnection, Organization};
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\CalDav\Contracts\{CalDavGatewayFactory, CalendarSource};
use App\Plugins\CalDav\Services\{CalDavRemoteCalendarGateway, CalendarPublishItem, EventCalendarSource, ScheduleCalendarSource};
use App\Plugins\Contracts\{CalendarPublisher, PluginCapability};
use App\Plugins\Support\Calendar\{RemoteCalendarEvent, RemoteCalendarPublishService};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use Spatie\IcalendarGenerator\Components\{Calendar as IcsCalendar, Event as IcsEvent};
use Throwable;

/**
 * On-Premise-Kalenderanbindung CalDAV (Feature 058, MVP-126).
 *
 * - **Publiziert** WorkDiary-Termine ({@see \App\Models\Event}) in einen
 *   externen CalDAV-Kalender (Nextcloud/ownCloud als Referenz, generisch
 *   RFC 4791) — Datenhoheit bleibt beim Kunden, ohne Microsoft-/Google-Konto.
 * - **Idempotent** über stabile UIDs + {@see \App\Models\ExternalReference}:
 *   Anlegen/Ändern/Löschen (bei Absage) erzeugen keine Dubletten.
 * - Pro Organisation konfiguriert ({@see CalDavConnection}: Basis-URL,
 *   App-Passwort verschlüsselt at-rest, Ziel-Collection).
 *
 * Kündigt {@see PluginCapability::CalendarPublish} an. Rückimport externer
 * Termine ist bewusst zweite Ausbaustufe.
 */
class CalDavPlugin extends AbstractPlugin implements CalendarPublisher {
    public const ID = 'caldav';

    /** ExternalReference-Typ des CalDAV-Publishs (Bestandsdaten — nie ändern). */
    public const EXT_TYPE_CALENDAR_OBJECT = 'calendar_object';

    public const SERVICE_PROVIDER = CalDavServiceProvider::class;

    public function name(): string {
        return 'CalDAV';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Publiziert Termine idempotent in einen externen CalDAV-Kalender (Nextcloud/ownCloud, RFC 4791) — On-Premise, ohne Microsoft-/Google-Konto.');
    }

    public function capabilities(): array {
        return [
            PluginCapability::CalendarPublish,
        ];
    }

    /**
     * Einheitlicher Einstieg (CalendarPublisher): publiziert Termine und – je
     * nach Scope der Anbindung – Dienstpläne/Urlaube der Organisation in alle
     * aktiven CalDAV-Anbindungen (idempotent). Scope-Auswahl pro Verbindung über
     * {@see CalDavConnection::publishesScope()} (Rang 17).
     */
    public function publishCalendar(Organization $organization): array {
        $counters = ['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0];

        $factory = app(CalDavGatewayFactory::class);
        $publisher = app(RemoteCalendarPublishService::class);

        /** @var array<string, CalendarSource> $sources */
        $sources = [
            'events' => app(EventCalendarSource::class),
            'schedule' => app(ScheduleCalendarSource::class),
        ];
        // Item-Listen sind org-weit (verbindungsunabhängig) → je Scope einmal berechnen.
        $itemsByScope = [];

        $connections = CalDavConnection::query()
            ->where('organization_id', $organization->id)
            ->get();

        foreach ($connections as $connection) {
            if (! $connection->isActive()) {
                continue;
            }
            try {
                $gateway = new CalDavRemoteCalendarGateway($factory->for($connection));
                foreach ($sources as $scope => $source) {
                    if (! $connection->publishesScope($scope)) {
                        continue;
                    }
                    $itemsByScope[$scope] ??= $source->itemsFor($organization);
                    $result = $publisher->publish(self::ID, $connection, $gateway, $itemsByScope[$scope], self::EXT_TYPE_CALENDAR_OBJECT);
                    foreach ($counters as $key => $value) {
                        $counters[$key] = $value + $result[$key];
                    }
                }
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        return $counters;
    }

    /**
     * Einzelnes terminartiges Element (MVP-331, Bauturbo A11 — Kalender-Kanal
     * der Benachrichtigungen): idempotent über die stabile UID in alle aktiven
     * Termin-Anbindungen (Scope `events`) der Organisation publiziert —
     * derselbe {@see RemoteCalendarPublishService}-Weg wie die Org-Termine.
     */
    public function publishCalendarItem(Organization $organization, RemoteCalendarEvent $item): array {
        $counters = ['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0];

        $factory = app(CalDavGatewayFactory::class);
        $publisher = app(RemoteCalendarPublishService::class);
        $publishItem = $this->toPublishItem($item);

        $connections = CalDavConnection::query()
            ->where('organization_id', $organization->id)
            ->get();

        foreach ($connections as $connection) {
            if (! $connection->isActive() || ! $connection->publishesScope('events')) {
                continue;
            }
            try {
                $result = $publisher->publish(self::ID, $connection, new CalDavRemoteCalendarGateway($factory->for($connection)), [$publishItem], self::EXT_TYPE_CALENDAR_OBJECT);
                foreach ($counters as $key => $value) {
                    $counters[$key] = $value + $result[$key];
                }
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        return $counters;
    }

    /**
     * Bildet das providerneutrale Element auf ein CalDAV-Publish-Item ab:
     * Einzel-ICS (ein VEVENT, lokale Zeiten wie die übrigen Publishes) mit
     * der stabilen UID; der Objektname ist deterministisch aus der UID
     * abgeleitet (erneutes Feuern → selbes Objekt, Update statt Duplikat).
     */
    private function toPublishItem(RemoteCalendarEvent $item): CalendarPublishItem {
        $ics = '';
        if (! $item->cancelled) {
            $vevent = IcsEvent::create($item->title)
                ->uniqueIdentifier($item->uid)
                ->startsAt($item->start)
                ->endsAt($item->end)
                ->withoutTimezone();
            if ($item->description !== null && $item->description !== '') {
                $vevent->description($item->description);
            }
            if ($item->location !== '') {
                $vevent->address($item->location);
            }

            $ics = IcsCalendar::create($item->title)
                ->productIdentifier((string) config('events.ics.product_id', '-//workDiary//Events//DE'))
                ->event($vevent)
                ->get();
        }

        return new CalendarPublishItem(
            uid: $item->uid,
            objectName: 'notify-' . CryptoHelper::hash($item->uid, HashAlgorithm::SHA1) . '.ics',
            ics: $ics,
            referenceableType: $item->referenceableType,
            referenceableId: $item->referenceableId,
            cancelled: $item->cancelled,
        );
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.caldav.index',
            'label' => __('CalDAV'),
            'icon' => 'event',
        ];
    }

    /** Per-Org-Konfiguration liegt in `caldav_connections` (Admin-Panel), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    /** Health-Check je Organisation: aktive Anbindung suchen und die Collection anpingen. */
    public function healthCheck(): PluginHealth {
        $org = $this->healthOrgContext();
        if ($org instanceof PluginHealth) {
            return $org;
        }

        $connection = CalDavConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof CalDavConnection) {
            return PluginHealth::degraded(__('Keine CalDAV-Anbindung hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('CalDAV-Anbindung ist deaktiviert oder unvollständig.'));
        }

        try {
            return app(CalDavGatewayFactory::class)->for($connection)->ping()
                ? PluginHealth::ok(__('Verbunden mit :url.', ['url' => $connection->base_url]))
                : PluginHealth::failing(__('CalDAV-Server nicht erreichbar oder Zugangsdaten ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('CalDAV-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }
}
