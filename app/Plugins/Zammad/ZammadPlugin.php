<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZammadPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Zammad;

use App\Models\{Organization, PluginSetting, ZammadConnection};
use App\Plugins\Contracts\{Plugin, PluginCapability, TaskSyncer};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Zammad\Contracts\ZammadGatewayFactory;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use Throwable;

/**
 * Helpdesk-/Ticketsystem-Anbindung Zammad (Feature 060, MVP-129).
 *
 * - Referenz-Provider der Anbindungs-Lückenanalyse: Tickets einer zugeordneten
 *   Zammad-Gruppe (Queue) kommen als WorkDiary-Aufgaben an, damit Zeiterfassung/
 *   Nachweise/Abrechnung dort laufen. Das Ticketsystem bleibt führend.
 * - Import ist **idempotent** über {@see \App\Models\ExternalReference}
 *   (Plugin `zammad`, Typ `ticket`); Replays erzeugen keine Dubletten.
 * - Polling ({@see Console\ZammadSyncCommand}) ist die verlässliche Quelle;
 *   Webhook ({@see Http\Controllers\ZammadWebhookController}) stößt nur an —
 *   ein Webhook-Ausfall führt nie zu Datenverlust.
 * - Pro Organisation konfiguriert ({@see ZammadConnection}: Basis-URL, Token
 *   verschlüsselt at-rest, Queue→Projekt-Zuordnung).
 *
 * Kündigt {@see PluginCapability::TaskSync} an (Aufgaben-Sync-Vertrag aus
 * Feature 055) — bewusst einbahnig (Import); Konflikt-/Inbox-Zähler bleiben 0.
 */
class ZammadPlugin implements Plugin, TaskSyncer {
    use PluginDefaults;

    public const ID = 'zammad';

    public const SERVICE_PROVIDER = ZammadServiceProvider::class;

    /** ExternalReference-Typ dieses Plugins. */
    public const EXT_TYPE_TICKET = 'ticket';

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Zammad';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Importiert Zammad-Tickets als Aufgaben (idempotent, Queue→Projekt-Zuordnung): Zeiterfassung und Abrechnung in WorkDiary, das Ticketsystem bleibt führend. Polling mit Webhook-Anstoß.');
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

        return (bool) config('plugins.zammad.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::TaskSync,
        ];
    }

    /**
     * Einheitlicher Sync-Einstieg (TaskSyncer): Ticket-Import über alle aktiven
     * Zammad-Anbindungen der Organisation. Einbahnig — `created` aus dem
     * Import, `unchanged` = übersprungene (bereits verknüpfte) Tickets; die
     * bidirektionalen Zähler bleiben 0.
     */
    public function syncTasks(Organization $organization): array {
        $counters = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflicts' => 0, 'inbox' => 0, 'failed' => 0];

        $factory = app(ZammadGatewayFactory::class);
        $importer = app(ZammadTicketImporter::class);

        $connections = ZammadConnection::query()
            ->where('organization_id', $organization->id)
            ->get();

        foreach ($connections as $connection) {
            if (! $connection->isActive()) {
                continue;
            }
            try {
                $result = $importer->import($connection, $factory->for($connection));
                $counters['created'] += $result['created'];
                $counters['unchanged'] += $result['skipped'];
                $counters['inbox'] += $result['inbox'];
            } catch (Throwable) {
                $counters['failed']++;
            }
        }

        return $counters;
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.zammad.index',
            'label' => __('Zammad'),
            'icon' => 'confirmation_number',
        ];
    }

    public function serviceProvider(): ?string {
        return ZammadServiceProvider::class;
    }

    /** Per-Org-Konfiguration liegt in `zammad_connections` (Admin-Panel), nicht in plugin_settings. */
    public function settingsSchema(): array {
        return [];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /**
     * Health-Check je Organisation: aktive Anbindung suchen und die Zammad-API
     * mit dem hinterlegten Token anpingen.
     */
    public function healthCheck(): PluginHealth {
        $org = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if (! $org instanceof Organization) {
            return PluginHealth::ok(__('Keine Organisation im Kontext.'));
        }

        $connection = ZammadConnection::query()->where('organization_id', $org->id)->first();
        if (! $connection instanceof ZammadConnection) {
            return PluginHealth::degraded(__('Keine Zammad-Anbindung hinterlegt.'));
        }
        if (! $connection->isActive()) {
            return PluginHealth::degraded(__('Zammad-Anbindung ist deaktiviert oder unvollständig.'));
        }

        try {
            return app(ZammadGatewayFactory::class)->for($connection)->ping()
                ? PluginHealth::ok(__('Verbunden mit :url.', ['url' => $connection->base_url]))
                : PluginHealth::failing(__('Zammad-API nicht erreichbar oder Token ungültig.'), 'unreachable');
        } catch (Throwable $e) {
            return PluginHealth::failing(__('Zammad-API-Fehler (:class).', ['class' => class_basename($e)]));
        }
    }
}
