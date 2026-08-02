<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Models\Organization;
use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Contracts\{Plugin, PluginCapability, TimeImporter};
use App\Plugins\Toggl\Sources\TogglApiClient;
use Carbon\CarbonImmutable;

/**
 * Toggl-Track-Import-Plugin.
 *
 * - Importiert Zeiteinträge aus Toggl Track per API (v9) oder Detailed-Report-CSV.
 * - Toggl-Clients werden auf Kunden, Toggl-Projekte auf Projekte gematcht
 *   (über ExternalReference bzw. Namen). Nicht Zuordenbares landet in einer
 *   Admin-Inbox zur manuellen Zuweisung (TIME_IMPORT).
 *
 * Plugin-Id ist "toggl". Pro Organisation konfigurierbar über plugin_settings;
 * ENV/config dient nur als Fallback.
 */
class TogglPlugin extends AbstractPlugin implements TimeImporter {
    public const ID = 'toggl';

    public const SERVICE_PROVIDER = TogglServiceProvider::class;

    public function name(): string {
        return 'Toggl Track';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Importiert Projekt- und Zeitdaten aus Toggl Track (API oder CSV-Export) und ordnet sie Kunden/Projekten zu.');
    }

    public function capabilities(): array {
        return [
            PluginCapability::TimeImport,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TimeImporter): API-Import über das konfigurierte Zeitfenster. */
    public function importTimeEntries(Organization $organization): array {
        $config = TogglConfig::resolve($organization->id);
        $days = max(1, (int) $config['sync_window_days']);
        $to = CarbonImmutable::now();

        return app(TogglImportService::class)->importFromApi($organization, $config, $to->subDays($days), $to);
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.toggl.index',
            'label' => __('Toggl-Import'),
            'icon' => 'schedule',
        ];
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'api_token', 'label' => __('Toggl API-Token'), 'type' => 'password', 'required' => true, 'help' => __('Profil → Einstellungen → API-Token in Toggl Track.')],
            ['key' => 'base_url', 'label' => __('API-Basis-URL'), 'type' => 'text', 'default' => TogglConfig::DEFAULT_BASE_URL],
            ['key' => 'workspace_id', 'label' => __('Workspace-ID'), 'type' => 'text', 'help' => __('Optional. Leer = alle Workspaces des Tokens.')],
            ['key' => 'sync_window_days', 'label' => __('Sync-Zeitfenster (Tage)'), 'type' => 'text', 'default' => '30', 'help' => __('Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.')],
            ['key' => 'default_billable', 'label' => __('Abrechenbar übernehmen'), 'type' => 'boolean', 'default' => true, 'help' => __('Wenn aus, werden importierte Zeiten nie als abrechenbar markiert.')],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],
            ['key' => 'export_enabled', 'label' => __('Zeit-Übertragung aktivieren'), 'type' => 'boolean', 'default' => false, 'help' => __('Überträgt in workDiary erfasste Zeiten gemappter Projekte nach Toggl (z. B. Fernwartungssitzungen). Angelegt wird immer für den Token-Inhaber; übertragene Einträge verhalten sich danach wie importierte — inklusive Abgleich in beide Richtungen und Löschungserkennung.')],
            ['key' => 'writeback', 'label' => __('Korrekturen zurückschreiben'), 'type' => 'boolean', 'default' => false, 'help' => __('Schreibt Korrekturen an bereits importierten Zeiten zurück nach Toggl (Änderung und Löschung). Wurde der Eintrag dort zwischenzeitlich geändert, wird nichts überschrieben — der Fall landet in der Integrations-Inbox. Abgerechnete Zeiten werden nie zurückgeschrieben.')],
        ];
    }

    /** Health-Check: pingt /me mit dem konfigurierten Token (ohne Token: degraded, Fehler: failing). */
    public function healthCheck(): PluginHealth {
        $config = TogglConfig::resolve();
        $client = new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id']);

        return PluginHealth::pingHealth(
            ping: fn (): bool => $client->ping(),
            unreachableMessage: __('Toggl-API nicht erreichbar oder Token ungültig.'),
            configured: $client->isConfigured(),
            notConfiguredMessage: __('Kein Toggl API-Token hinterlegt.'),
            notConfiguredStatus: PluginHealth::STATUS_DEGRADED,
            errorStatus: PluginHealth::STATUS_FAILING,
            okMessage: 'toggl: ok',
        );
    }
}
