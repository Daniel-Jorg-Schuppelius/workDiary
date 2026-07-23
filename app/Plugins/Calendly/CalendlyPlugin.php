<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly;

use App\Models\{CalendlyConnection, Organization, PluginSetting};
use App\Plugins\Calendly\Api\CalendlyClient;
use App\Plugins\Calendly\Services\CalendlyBackfillService;
use App\Plugins\Contracts\{AppointmentSyncer, Plugin, PluginCapability};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\PluginOrgContext;
use Throwable;

/**
 * Calendly-Terminbuchung-Plugin (Feature 095).
 *
 * - Empfängt extern gebuchte Termine per Webhook (`invitee.created`/`.canceled`)
 *   + Polling-Backfill und legt sie als bestätigungspflichtige Terminwünsche
 *   (`appointment_requests`, Zustand `requested`) an — kein Externer schreibt
 *   direkt in den Dienstplan (Zweiphasigkeit).
 * - Invitees werden auf Kunden gematcht; Unzuordenbares landet in der
 *   Zuordnungs-Inbox.
 *
 * Plugin-Id ist "calendly", pro Organisation konfigurierbar; OAuth-Client-ID/
 * -Secret sind installationsweit (ENV).
 */
class CalendlyPlugin implements AppointmentSyncer, Plugin {
    use PluginDefaults;

    public const ID = 'calendly';

    public const SERVICE_PROVIDER = CalendlyServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Calendly';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Empfängt extern über Calendly gebuchte Termine als bestätigungspflichtige Terminwünsche und erzeugt Einmal-Buchungslinks.');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.calendly.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::AppointmentSync,
        ];
    }

    /** Einheitlicher Sync-Einstieg (AppointmentSyncer): Polling-Backfill über das Zeitfenster. */
    public function syncAppointments(Organization $organization): array {
        return app(CalendlyBackfillService::class)->sync($organization);
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.calendly.index',
            'label' => __('Calendly'),
            'icon' => 'event',
        ];
    }

    public function serviceProvider(): ?string {
        return CalendlyServiceProvider::class;
    }

    public function settingsSchema(): array {
        // OAuth-Client-ID/-Secret sind installationsweit (ENV); die Verbindung
        // wird über den OAuth-Flow im Admin-Panel hergestellt.
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /** Health-Check: aktive Verbindung + /users/me-Ping. */
    public function healthCheck(): PluginHealth {
        if (! CalendlyConfig::isConfigured()) {
            return PluginHealth::degraded(__('Calendly Client-ID/Secret nicht konfiguriert.'));
        }

        $org = PluginOrgContext::currentOrNull();
        if (! $org instanceof Organization) {
            return PluginHealth::ok();
        }

        $connection = CalendlyConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof CalendlyConnection || ! $connection->isActive()) {
            return PluginHealth::degraded(__('Keine aktive Calendly-Verbindung.'));
        }

        try {
            return (new CalendlyClient($connection))->ping()
                ? PluginHealth::ok('calendly: ok')
                : PluginHealth::failing(__('Calendly-API nicht erreichbar oder Token ungültig.'));
        } catch (Throwable $e) {
            return PluginHealth::failing($e->getMessage());
        }
    }
}
