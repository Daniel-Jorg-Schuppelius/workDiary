<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Kimai;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive Kimai-Konfiguration (CSV + API): plugin_settings der gebundenen
 * Organisation vor `config('plugins.kimai.*')` — Lookup/Cast im
 * {@see PluginSettingsResolver} (C10). Analog {@see \App\Plugins\Toggl\TogglConfig}.
 *
 * @phpstan-type KimaiSettings array{enabled: bool, default_billable: bool, default_user_id: ?int, single_user_mode: bool, base_url: ?string, api_token: ?string, api_all_users: bool, sync_window_days: int, default_activity_id: ?int, export_enabled: bool, push_on_create: bool, writeback: bool}
 */
class KimaiConfig {
    /**
     * @return array{enabled: bool, default_billable: bool, default_user_id: ?int, single_user_mode: bool, base_url: ?string, api_token: ?string, api_all_users: bool, sync_window_days: int, default_activity_id: ?int, export_enabled: bool, push_on_create: bool, writeback: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(KimaiPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'default_billable' => $r->bool('default_billable', true),
            'default_user_id' => $r->intOrNull('default_user_id'),
            // Einbenutzer-Modus (MVP-509): siehe TogglConfig — Standard-Benutzer nur bei ausdrücklicher Wahl.
            'single_user_mode' => $r->bool('single_user_mode', false),
            'base_url' => $r->string('base_url', trim: true),
            'api_token' => $r->string('api_token', trim: true),
            'api_all_users' => $r->bool('api_all_users', true),
            'sync_window_days' => max(1, $r->int('sync_window_days', 30)),
            'default_activity_id' => $r->intOrNull('default_activity_id'),
            'export_enabled' => $r->bool('export_enabled', false),
            'push_on_create' => $r->bool('push_on_create', false),
            'writeback' => $r->bool('writeback', false),
        ];
    }
}
