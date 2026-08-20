<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Clockify;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive Clockify-Konfiguration (CSV + API): plugin_settings der gebundenen
 * Organisation vor `config('plugins.clockify.*')` — Lookup/Cast im
 * {@see PluginSettingsResolver} (C10). Analog {@see \App\Plugins\Kimai\KimaiConfig}.
 *
 * @phpstan-type ClockifySettings array{enabled: bool, default_billable: bool, default_user_id: ?int, single_user_mode: bool, api_key: ?string, workspace_id: ?string, base_url: string, reports_base_url: string, sync_window_days: int, writeback: bool, export_enabled: bool}
 */
class ClockifyConfig {
    public const DEFAULT_BASE_URL = 'https://api.clockify.me/api';

    public const DEFAULT_REPORTS_BASE_URL = 'https://reports.api.clockify.me/v1';

    /**
     * @return array{enabled: bool, default_billable: bool, default_user_id: ?int, single_user_mode: bool, api_key: ?string, workspace_id: ?string, base_url: string, reports_base_url: string, sync_window_days: int, writeback: bool, export_enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(ClockifyPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'default_billable' => $r->bool('default_billable', true),
            'default_user_id' => $r->intOrNull('default_user_id'),
            // Einbenutzer-Modus (MVP-509): siehe TogglConfig — Standard-Benutzer nur bei ausdrücklicher Wahl.
            'single_user_mode' => $r->bool('single_user_mode', false),
            'api_key' => $r->string('api_key', trim: true),
            'workspace_id' => $r->string('workspace_id', trim: true),
            'base_url' => $r->string('base_url', trim: true) ?? self::DEFAULT_BASE_URL,
            'reports_base_url' => $r->string('reports_base_url', trim: true) ?? self::DEFAULT_REPORTS_BASE_URL,
            'sync_window_days' => max(1, $r->int('sync_window_days', 30)),
            'writeback' => $r->bool('writeback', false),
            'export_enabled' => $r->bool('export_enabled', false),
        ];
    }
}
