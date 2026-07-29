<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Kimai;

use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\{Plugin, PluginCapability, TimeImporter};
use App\Plugins\Kimai\Exceptions\KimaiApiException;
use App\Plugins\Kimai\Sources\KimaiApiClient;
use App\Plugins\{PluginDefaults, PluginHealth};
use App\Plugins\Support\PluginOrgContext;

/**
 * Kimai-Plugin (Migrationsimport MVP-134 + API-Rückkanal).
 *
 * Importiert Zeiteinträge aus einem Kimai-Timesheet-CSV-Export oder direkt
 * über die Kimai-2.x-REST-API (Bearer-Token, `php-api-toolkit`-Fundament);
 * optional werden in workDiary erfasste Zeiten als Kimai-Timesheets
 * zurückgebucht ({@see KimaiExportService}, OpenProject-Muster). Kimai-Kunden
 * werden auf Kunden, Kimai-Projekte auf Projekte gematcht (über
 * ExternalReference bzw. Namen); nicht Zuordenbares landet in der universellen
 * Zuordnungs-Inbox ({@see KimaiGroupBooker}).
 *
 * Plugin-Id ist "kimai". Pro Organisation konfigurierbar über plugin_settings.
 */
class KimaiPlugin implements Plugin, TimeImporter {
    use PluginDefaults;

    public const ID = 'kimai';

    /** Vom {@see \App\Providers\PluginServiceProvider} zur Provider-Registrierung ausgewertet. */
    public const SERVICE_PROVIDER = KimaiServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'Kimai';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Importiert Zeiteinträge aus Kimai (CSV-Export oder REST-API) und bucht erfasste Zeiten optional als Kimai-Timesheets zurück.');
    }

    public function isEnabled(): bool {
        $org = PluginOrgContext::currentOrNull();
        if ($org instanceof Organization) {
            $row = PluginSetting::forOrganization($org->id, self::ID);
            if ($row->exists) {
                return $row->enabled;
            }
        }

        return (bool) config('plugins.kimai.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::TimeImport,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TimeImporter): API-Import über das konfigurierte Zeitfenster. */
    public function importTimeEntries(Organization $organization): array {
        $config = KimaiConfig::resolve($organization->id);

        return app(KimaiImportService::class)->importFromApi($organization, $config);
    }

    /** Health-Check: pingt die Kimai-API; ohne API-Zugang ist CSV der reguläre Modus. */
    public function healthCheck(): PluginHealth {
        $config = KimaiConfig::resolve();
        $client = new KimaiApiClient($config['api_token'], $config['base_url']);

        if (! $client->isConfigured()) {
            return PluginHealth::ok(__('CSV-Modus — kein API-Zugang hinterlegt.'));
        }

        try {
            return $client->ping()
                ? PluginHealth::ok()
                : PluginHealth::degraded(__('Kimai-API antwortet nicht (Ping fehlgeschlagen).'));
        } catch (KimaiApiException $e) {
            return PluginHealth::degraded($e->getMessage());
        } catch (\Throwable $e) {
            return PluginHealth::degraded(__('Kimai-API nicht erreichbar: :message', ['message' => $e->getMessage()]));
        }
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.kimai.index',
            'label' => __('Kimai-Import'),
            'icon' => 'schedule',
        ];
    }

    public function serviceProvider(): ?string {
        return KimaiServiceProvider::class;
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'default_billable', 'label' => __('Abrechenbar übernehmen'), 'type' => 'boolean', 'default' => true, 'help' => __('Wenn aus, werden importierte Zeiten nie als abrechenbar markiert.')],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],
            ['key' => 'base_url', 'label' => __('Kimai-Basis-URL'), 'type' => 'text', 'help' => __('Adresse der Kimai-Instanz, z. B. https://kimai.example.com (ohne /api).')],
            ['key' => 'api_token', 'label' => __('Kimai API-Token'), 'type' => 'password', 'help' => __('Kimai 2.x: Profil → API-Zugang. Zusammen mit der Basis-URL schaltet das den API-Import frei.')],
            ['key' => 'api_all_users', 'label' => __('Zeiten aller Benutzer abrufen'), 'type' => 'boolean', 'default' => true, 'help' => __('Braucht in Kimai das Recht view_other_timesheet; sonst kommen nur die Zeiten des Token-Benutzers.')],
            ['key' => 'sync_window_days', 'label' => __('Sync-Zeitfenster (Tage)'), 'type' => 'text', 'default' => '30', 'help' => __('Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.')],
            ['key' => 'export_enabled', 'label' => __('Rückbuchung aktivieren'), 'type' => 'boolean', 'default' => false, 'help' => __('Bucht in workDiary erfasste Zeiten gemappter Projekte als Kimai-Timesheets zurück.')],
            ['key' => 'default_activity_id', 'label' => __('Kimai-Activity-ID für Rückbuchungen'), 'type' => 'text', 'help' => __('Pflicht für die Rückbuchung — Kimai verlangt eine Tätigkeit je Timesheet.')],
            ['key' => 'writeback', 'label' => __('Korrekturen zurückschreiben'), 'type' => 'boolean', 'default' => false, 'help' => __('Schreibt Korrekturen an bereits importierten Zeiten zurück nach Kimai (Änderung und Löschung). Wurde der Eintrag dort zwischenzeitlich geändert, wird nichts überschrieben — der Fall landet in der Integrations-Inbox. Abgerechnete Zeiten werden nie zurückgeschrieben.')],
        ];
    }

    public function isPerOrganization(): bool {
        return true;
    }
}
