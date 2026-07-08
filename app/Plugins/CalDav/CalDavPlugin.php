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

use App\Models\{CalDavConnection, Organization, PluginSetting};
use App\Plugins\CalDav\Contracts\{CalDavGatewayFactory, CalendarSource};
use App\Plugins\CalDav\Services\{CalendarPublishService, EventCalendarSource, ScheduleCalendarSource};
use App\Plugins\Contracts\{CalendarPublisher, Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth};
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
class CalDavPlugin implements CalendarPublisher, Plugin {
    use PluginDefaults;

    public const ID = 'caldav';

    public const SERVICE_PROVIDER = CalDavServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'CalDAV';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Publiziert Termine idempotent in einen externen CalDAV-Kalender (Nextcloud/ownCloud, RFC 4791) — On-Premise, ohne Microsoft-/Google-Konto.');
    }

    public function isEnabled(): bool {
        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                $row = PluginSetting::forOrganization($org->id, self::ID);
                if ($row->exists) {
                    return $row->enabled;
                }
            }
        }

        return (bool) config('plugins.caldav.enabled', false);
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
        $publisher = app(CalendarPublishService::class);

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
                $gateway = $factory->for($connection);
                foreach ($sources as $scope => $source) {
                    if (! $connection->publishesScope($scope)) {
                        continue;
                    }
                    $itemsByScope[$scope] ??= $source->itemsFor($organization);
                    $result = $publisher->publish($connection, $gateway, $itemsByScope[$scope]);
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

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.caldav.index',
            'label' => __('CalDAV'),
            'icon' => 'event',
        ];
    }

    public function serviceProvider(): ?string {
        return CalDavServiceProvider::class;
    }

    /** Per-Org-Konfiguration liegt in `caldav_connections` (Admin-Panel), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health-Check je Organisation: aktive Anbindung suchen und die Collection anpingen. */
    public function healthCheck(): PluginHealth {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
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
