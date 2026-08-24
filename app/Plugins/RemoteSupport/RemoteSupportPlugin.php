<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Models\{Asset, Organization};
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{Plugin, PluginCapability, SlotRenderer, TimeImporter};
use App\Plugins\RemoteSupport\Providers\{AnyDeskClient, TeamViewerClient};
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Fernwartungs-Plugin (AnyDesk + TeamViewer).
 *
 * - Hinterlegt je Gerät (Asset) die AnyDesk-/TeamViewer-ID in external_references.
 * - Importiert die Verbindungs-Reports beider Dienste und legt je Sitzung einen
 *   TimeEntry im Standardprojekt des zugeordneten Kunden an (TIME_IMPORT).
 *
 * Plugin-Id ist "remote-support". Pro Organisation konfigurierbar über
 * plugin_settings; ENV dient nur als Fallback.
 */
class RemoteSupportPlugin extends AbstractPlugin implements SlotRenderer, TimeImporter {
    public const ID = 'remote-support';

    public const SERVICE_PROVIDER = RemoteSupportServiceProvider::class;

    public function name(): string {
        return 'Fernwartung';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Speichert AnyDesk-/TeamViewer-IDs an Geräten und importiert Verbindungen als Zeiteinträge im Standardprojekt des Kunden.');
    }

    public function capabilities(): array {
        return [
            PluginCapability::TimeImport,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TimeImporter): importiert Sitzungen über das konfigurierte Zeitfenster. */
    public function importTimeEntries(Organization $organization): array {
        $config = RemoteSupportConfig::resolve($organization->id);
        $days = max(1, (int) $config['sync_window_days']);
        $to = CarbonImmutable::now();

        return app(RemoteSessionImporter::class)->import($organization, $config, $to->subDays($days), $to);
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.plugins.edit',
            'label' => __('Fernwartungs-Einstellungen'),
            'icon' => 'support_agent',
        ];
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'sync_window_days', 'label' => __('Sync-Zeitfenster (Tage)'), 'type' => 'text', 'default' => '2', 'help' => __('Wie viele Tage rückwirkend pro Lauf abgefragt werden.')],
            ['key' => 'default_billable', 'label' => __('Importierte Sitzungen abrechenbar'), 'type' => 'boolean', 'default' => true],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],

            ['key' => 'anydesk_enabled', 'label' => __('AnyDesk aktiv'), 'type' => 'boolean', 'default' => false],
            ['key' => 'anydesk_license_id', 'label' => __('AnyDesk Lizenz-ID'), 'type' => 'text', 'help' => __('Numerische Lizenz-ID (z. B. 1438129266231705) — nicht der Lizenz-Schlüssel aus Buchstaben/Ziffern.')],
            ['key' => 'anydesk_api_key', 'label' => __('AnyDesk API-Passwort'), 'type' => 'password', 'help' => __('API-Passwort der AnyDesk-Lizenz (Request-Signierung).')],
            ['key' => 'anydesk_base_url', 'label' => __('AnyDesk API-Basis-URL'), 'type' => 'text', 'default' => 'https://v1.api.anydesk.com:8081'],

            ['key' => 'teamviewer_enabled', 'label' => __('TeamViewer aktiv'), 'type' => 'boolean', 'default' => false],
            ['key' => 'teamviewer_api_key', 'label' => __('TeamViewer Script-Token'), 'type' => 'password', 'help' => __('Script-Token mit Connection-Report-Berechtigung.')],
            ['key' => 'teamviewer_base_url', 'label' => __('TeamViewer API-Basis-URL'), 'type' => 'text', 'default' => 'https://webapi.teamviewer.com/api/v1'],
        ];
    }

    /**
     * Pingt die aktiven Provider. Antwortet mindestens einer, gilt das Plugin
     * als gesund; kein aktiver/konfigurierter Provider → degraded.
     */
    public function healthCheck(): PluginHealth {
        $config = RemoteSupportConfig::resolve();
        $providers = array_filter(app(RemoteSessionImporter::class)->providersFor($config), fn($p) => $p->isConfigured());

        if ($providers === []) {
            return PluginHealth::degraded(__('Kein Fernwartungs-Anbieter konfiguriert.'));
        }

        $messages = [];
        $anyOk = false;
        foreach ($providers as $provider) {
            try {
                $ok = $provider->ping();
                $anyOk = $anyOk || $ok;
                $messages[] = sprintf('%s: %s', $provider->id(), $ok ? 'ok' : 'fail');
            } catch (Throwable $e) {
                $messages[] = sprintf('%s: %s', $provider->id(), $e->getMessage());
            }
        }

        $text = implode(' · ', $messages);

        return $anyOk ? PluginHealth::ok($text) : PluginHealth::failing($text);
    }

    /**
     * Rendert das Fernwartungs-Panel in der Asset-Detailansicht — nur, wenn das
     * Plugin aktiv ist und das Gerät eine fernwartbare Unterkategorie hat
     * (Arbeitsplatz, Server, Notebook).
     */
    public function renderActions(string $slot, mixed $context = null): ?string {
        if (! $this->isEnabled()) {
            return null;
        }
        if ($slot !== 'asset-show.aside' || ! $context instanceof Asset) {
            return null;
        }
        if (! in_array($context->category_code, RemoteDeviceRegistry::REMOTE_CATEGORY_CODES, true)) {
            return null;
        }

        $devices = app(RemoteDeviceRegistry::class);

        $organization = $context->organization;
        $pendingCount = $organization instanceof Organization
            ? app(RemotePendingAssignmentService::class)->openPendingGroups($organization)->sum('count')
            : 0;

        return view('remote-support::_panel', [
            'asset' => $context,
            'anydeskIds' => $devices->remoteIds($context, AnyDeskClient::ID),
            'teamviewerIds' => $devices->remoteIds($context, TeamViewerClient::ID),
            'pendingCount' => (int) $pendingCount,
            // Ziele für „Fernwartungsdaten übertragen" (Duplikat-Bereinigung).
            'mergeTargets' => \App\Models\Asset::query()
                ->whereIn('category_code', RemoteDeviceRegistry::REMOTE_CATEGORY_CODES)
                ->whereKeyNot($context->getKey())
                ->orderBy('name')
                ->get(['id', 'name', 'asset_no']),
        ])->render();
    }
}
