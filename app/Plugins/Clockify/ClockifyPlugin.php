<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Clockify;

use App\Models\{Organization, PluginSetting};
use App\Plugins\Clockify\Exceptions\ClockifyApiException;
use App\Plugins\Clockify\Sources\ClockifyApiClient;
use App\Plugins\Contracts\{Plugin, PluginCapability, TimeImporter};
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\PluginOrgContext;

/**
 * Clockify-Import-Plugin (Migrationsimport, MVP-134).
 *
 * Importiert Zeiteinträge aus einem Clockify-Detailed-Report-CSV oder direkt
 * über die Clockify-Reports-API (`X-Api-Key`, `php-api-toolkit`-Fundament).
 * Clockify-Clients werden auf Kunden, Clockify-Projekte auf Projekte gematcht
 * (gemeinsame Pipeline {@see \App\Plugins\Support\MatchingTimeImportService});
 * nicht Zuordenbares landet in der universellen Zuordnungs-Inbox
 * ({@see ClockifyGroupBooker}). Free-Plan-Hinweis: 30 API-Requests/h — für
 * Free-Konten ist der CSV-Weg der richtige.
 *
 * Plugin-Id ist "clockify". Pro Organisation konfigurierbar über plugin_settings.
 */
class ClockifyPlugin implements Plugin, TimeImporter {
    use PluginDefaults;

    public const ID = 'clockify';

    /** Vom {@see \App\Providers\PluginServiceProvider} zur Provider-Registrierung ausgewertet. */
    public const SERVICE_PROVIDER = ClockifyServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Clockify';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Importiert Zeiteinträge aus Clockify (Detailed-Report-CSV oder REST-API) und ordnet sie Kunden/Projekten zu.');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.clockify.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::TimeImport,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TimeImporter): API-Import über das konfigurierte Zeitfenster. */
    public function importTimeEntries(Organization $organization): array {
        $config = ClockifyConfig::resolve($organization->id);

        return app(ClockifyImportService::class)->importFromApi($organization, $config);
    }

    /** Health-Check: löst den API-Benutzer auf; ohne API-Key ist CSV der reguläre Modus. */
    public function healthCheck(): PluginHealth {
        $config = ClockifyConfig::resolve();
        $client = new ClockifyApiClient($config['api_key'], $config['base_url'], $config['reports_base_url'], $config['workspace_id']);

        if (! $client->isConfigured()) {
            return PluginHealth::ok(__('CSV-Modus — kein API-Key hinterlegt.'));
        }

        try {
            $client->currentUser();

            return PluginHealth::ok();
        } catch (ClockifyApiException $e) {
            return PluginHealth::degraded($e->getMessage());
        } catch (\Throwable $e) {
            return PluginHealth::degraded(__('Clockify-API nicht erreichbar: :message', ['message' => $e->getMessage()]));
        }
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.clockify.index',
            'label' => __('Clockify-Import'),
            'icon' => 'schedule',
        ];
    }

    public function serviceProvider(): ?string {
        return ClockifyServiceProvider::class;
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'default_billable', 'label' => __('Abrechenbar übernehmen'), 'type' => 'boolean', 'default' => true, 'help' => __('Wenn aus, werden importierte Zeiten nie als abrechenbar markiert.')],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],
            ['key' => 'api_key', 'label' => __('Clockify API-Key'), 'type' => 'password', 'help' => __('Clockify → Profil → Advanced → API. Free-Plan erlaubt nur 30 Requests/h — für Free-Konten den CSV-Weg nutzen.')],
            ['key' => 'workspace_id', 'label' => __('Workspace-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Standard-Workspace des API-Keys.')],
            ['key' => 'base_url', 'label' => __('API-Basis-URL'), 'type' => 'text', 'default' => ClockifyConfig::DEFAULT_BASE_URL, 'help' => __('Regionale Instanzen abweichend, z. B. https://euc1.api.clockify.me/api.')],
            ['key' => 'reports_base_url', 'label' => __('Reports-API-Basis-URL'), 'type' => 'text', 'default' => ClockifyConfig::DEFAULT_REPORTS_BASE_URL],
            ['key' => 'sync_window_days', 'label' => __('Sync-Zeitfenster (Tage)'), 'type' => 'text', 'default' => '30', 'help' => __('Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.')],
            ['key' => 'writeback', 'label' => __('Korrekturen zurückschreiben'), 'type' => 'boolean', 'default' => false, 'help' => __('Schreibt Korrekturen an bereits importierten Zeiten zurück nach Clockify (Änderung und Löschung). Wurde der Eintrag dort zwischenzeitlich geändert, wird nichts überschrieben — der Fall landet in der Integrations-Inbox. Abgerechnete Zeiten werden nie zurückgeschrieben.')],
        ];
    }

    public function isPerOrganization(): bool {
        return true;
    }
}
