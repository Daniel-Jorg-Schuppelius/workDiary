<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GoogleCalendarPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\GoogleCalendar;

use App\Models\{GoogleCalendarConnection, Organization, PluginSetting};
use App\Plugins\Contracts\{CalendarPublisher, Plugin, PluginCapability};
use App\Plugins\GoogleCalendar\Api\GoogleCalendarClient;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\Calendar\{OrganizationEventSource, RemoteCalendarEvent, RemoteCalendarPublishService};
use App\Plugins\Support\PluginOrgContext;
use Closure;
use Throwable;

/**
 * Google-Kalender-Anbindung (MVP-328, Bauturbo A8) — Nur-Publish-Pilot
 * neben CalDAV/ICS.
 *
 * - **Publiziert** WorkDiary-Termine ({@see \App\Models\Event}) über die
 *   Google Calendar API v3 in einen wählbaren Kalender des verbundenen
 *   Google-Kontos (OAuth2 Authorization-Code + PKCE, offline access).
 * - **Idempotent** über stabile UIDs (deterministische Event-ID) +
 *   {@see \App\Models\ExternalReference}: Anlegen/Ändern/Löschen (bei Absage)
 *   erzeugen keine Dubletten — CalDAV-Muster.
 * - Pro Organisation verbunden ({@see GoogleCalendarConnection}, Tokens
 *   verschlüsselt at-rest); Rückimport externer Termine ist bewusst NICHT
 *   Teil des Piloten.
 *
 * Kündigt {@see PluginCapability::CalendarPublish} an.
 */
class GoogleCalendarPlugin implements CalendarPublisher, Plugin {
    use PluginDefaults;

    public const ID = 'google_calendar';

    public const SERVICE_PROVIDER = GoogleCalendarServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Google Calendar';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('google_calendar.plugin_description');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.google_calendar.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::CalendarPublish,
        ];
    }

    /**
     * Einheitlicher Einstieg (CalendarPublisher): publiziert die Termine der
     * Organisation idempotent in den Ziel-Kalender der Google-Verbindung.
     */
    public function publishCalendar(Organization $organization): array {
        return $this->publishItems($organization, fn(): array => app(OrganizationEventSource::class)->itemsFor($organization));
    }

    /**
     * Einzelnes terminartiges Element (MVP-331, Bauturbo A11 — Kalender-Kanal
     * der Benachrichtigungen): identischer idempotenter Publish-Weg, nur mit
     * genau einem Element statt der Org-Terminquelle.
     */
    public function publishCalendarItem(Organization $organization, RemoteCalendarEvent $item): array {
        return $this->publishItems($organization, fn(): array => [$item]);
    }

    /**
     * Gemeinsamer Publish-Kern: aktive Verbindung auflösen, Elemente über den
     * A8-Publish-Service abgleichen; ohne Verbindung stiller No-Op.
     *
     * @param  Closure(): list<RemoteCalendarEvent>  $items
     * @return array{published: int, deleted: int, unchanged: int, failed: int}
     */
    private function publishItems(Organization $organization, Closure $items): array {
        $counters = ['published' => 0, 'deleted' => 0, 'unchanged' => 0, 'failed' => 0];

        $connection = GoogleCalendarConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof GoogleCalendarConnection || ! $connection->isActive()) {
            return $counters;
        }

        try {
            $gateway = new GoogleCalendarClient($connection);
            $result = app(RemoteCalendarPublishService::class)->publish(self::ID, $connection, $gateway, $items());
            foreach ($counters as $key => $value) {
                $counters[$key] = $value + $result[$key];
            }
        } catch (Throwable) {
            $counters['failed']++;
        }

        return $counters;
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.google-calendar.index',
            'label' => __('google_calendar.title'),
            'icon' => 'event',
        ];
    }

    public function serviceProvider(): ?string {
        return GoogleCalendarServiceProvider::class;
    }

    /** Keine per-Org-Secrets: Client-ID/-Secret sind installationsweit (ENV). */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health-Check je Organisation: billige Probe über die Kalenderliste. */
    public function healthCheck(): PluginHealth {
        if (! GoogleCalendarConfig::isConfigured()) {
            return PluginHealth::degraded(__('google_calendar.health.not_configured'));
        }

        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('google_calendar.health.no_org_context'));
        }

        $connection = GoogleCalendarConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof GoogleCalendarConnection || $connection->status === GoogleCalendarConnection::STATUS_DISCONNECTED) {
            return PluginHealth::degraded(__('google_calendar.health.no_connection'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('google_calendar.health.inactive'));
        }

        try {
            return (new GoogleCalendarClient($connection))->ping()
                ? PluginHealth::ok(__('google_calendar.health.ok'))
                : PluginHealth::failing(__('google_calendar.health.failing'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('google_calendar.health.error', ['class' => class_basename($e)]));
        }
    }
}
