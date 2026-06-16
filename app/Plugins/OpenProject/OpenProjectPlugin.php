<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject;

use App\Models\{Organization, PluginSetting};
use App\Plugins\Contracts\{Plugin, PluginCapability, TimeImporter};
use App\Plugins\OpenProject\Services\OpenProjectImportService;
use App\Plugins\OpenProject\Sources\OpenProjectApiClient;
use App\Plugins\{PluginDefaults, PluginHealth};
use Carbon\CarbonImmutable;
use Throwable;

/**
 * OpenProject-Anbindung (API v3).
 *
 * - Synchronisiert Projekte + Work Packages als Mapping-Quelle und importiert
 *   Zeiteinträge per API. OpenProject-Projekte werden auf workDiary-Projekte,
 *   Work Packages auf Aufgaben gematcht (über {@see \App\Models\ExternalReference}
 *   bzw. Namen). Nicht Zuordenbares landet in einer Admin-Inbox (TIME_IMPORT).
 * - Bucht in workDiary erfasste Zeiten als time_entries nach OpenProject zurück
 *   ({@see \App\Plugins\OpenProject\Services\OpenProjectExportService}).
 *
 * Plugin-Id ist "openproject". Pro Organisation konfigurierbar über
 * plugin_settings; ENV/config dient nur als Fallback.
 */
class OpenProjectPlugin implements Plugin, TimeImporter {
    use PluginDefaults;

    public const ID = 'openproject';

    public const SERVICE_PROVIDER = OpenProjectServiceProvider::class;

    public function id(): string {
        return self::ID;
    }

    public function name(): string {
        return 'OpenProject';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return __('Synchronisiert Projekte und Work Packages aus OpenProject, importiert Zeiteinträge und bucht erfasste Zeiten zurück (API v3).');
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

        return (bool) config('plugins.openproject.enabled', false);
    }

    public function capabilities(): array {
        return [
            PluginCapability::TimeImport,
        ];
    }

    /** Einheitlicher Sync-Einstieg (TimeImporter): API-Import über das konfigurierte Zeitfenster. */
    public function importTimeEntries(Organization $organization): array {
        $config = OpenProjectConfig::resolve($organization->id);
        $days = max(1, (int) $config['sync_window_days']);
        $to = CarbonImmutable::now();

        return app(OpenProjectImportService::class)->importFromApi($organization, $config, $to->subDays($days), $to);
    }

    public function adminPanel(): ?array {
        return [
            'route' => 'admin.openproject.index',
            'label' => __('OpenProject'),
            'icon' => 'schedule',
        ];
    }

    public function serviceProvider(): ?string {
        return OpenProjectServiceProvider::class;
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('Instanz-URL'), 'type' => 'text', 'required' => true, 'help' => __('z. B. https://openproject.example.com (mit oder ohne /api/v3).')],
            ['key' => 'api_token', 'label' => __('API-Token'), 'type' => 'password', 'required' => true, 'help' => __('Mein Konto → Zugriffstoken → API in OpenProject.')],
            ['key' => 'sync_window_days', 'label' => __('Sync-Zeitfenster (Tage)'), 'type' => 'text', 'default' => '30', 'help' => __('Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.')],
            ['key' => 'default_billable', 'label' => __('Abrechenbar übernehmen'), 'type' => 'boolean', 'default' => true, 'help' => __('Wenn aus, werden importierte Zeiten nie als abrechenbar markiert.')],
            ['key' => 'default_user_id', 'label' => __('Zeiten buchen für Benutzer-ID'), 'type' => 'text', 'help' => __('Optional. Leer = Organisations-Owner bzw. erster Benutzer.')],
            ['key' => 'default_activity_id', 'label' => __('OpenProject-Activity-ID (Rückbuchung)'), 'type' => 'text', 'help' => __('TimeEntriesActivity-ID, unter der zurückgebuchte Zeiten angelegt werden (Pflicht für den Export).')],
            ['key' => 'create_missing_projects', 'label' => __('Fehlende Projekte/Aufgaben anlegen'), 'type' => 'boolean', 'default' => false, 'help' => __('Beim Struktur-Sync fehlende workDiary-Projekte/Aufgaben automatisch anlegen statt nur zuzuordnen.')],
        ];
    }

    public function isPerOrganization(): bool {
        return true;
    }

    /**
     * Validiert die Settings vor dem Speichern: Token + URL sind Pflicht, die
     * URL muss eine gültige http(s)-Adresse sein.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public function validateSettings(array $settings): array {
        $errors = [];

        $baseUrl = trim((string) ($settings['base_url'] ?? ''));
        if ($baseUrl === '') {
            $errors['base_url'] = __('Die Instanz-URL ist erforderlich.');
        } elseif (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            $errors['base_url'] = __('Die Instanz-URL muss mit http:// oder https:// beginnen.');
        }

        if (trim((string) ($settings['api_token'] ?? '')) === '') {
            $errors['api_token'] = __('Das API-Token ist erforderlich.');
        }

        return $errors;
    }

    /** Health-Check: pingt /api/v3/users/me mit dem konfigurierten Token. */
    public function healthCheck(): PluginHealth {
        $config = OpenProjectConfig::resolve();
        $client = new OpenProjectApiClient($config['api_token'], $config['base_url']);

        if (! $client->isConfigured()) {
            return PluginHealth::degraded(__('Keine OpenProject-URL oder kein API-Token hinterlegt.'));
        }

        try {
            return $client->ping()
                ? PluginHealth::ok('openproject: ok')
                : PluginHealth::failing(__('OpenProject-API nicht erreichbar oder Token ungültig.'));
        } catch (Throwable $e) {
            return PluginHealth::failing($e->getMessage());
        }
    }
}
