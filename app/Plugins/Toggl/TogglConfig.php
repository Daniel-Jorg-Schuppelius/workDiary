<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Liefert die effektive Toggl-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf: plugin_settings der gebundenen Organisation vor
 * config('plugins.toggl.*') — Lookup/Cast im {@see PluginSettingsResolver} (C10).
 */
class TogglConfig {
    public const DEFAULT_BASE_URL = 'https://api.track.toggl.com/api/v9';

    /**
     * @return array{enabled: bool, api_token: ?string, base_url: string, workspace_id: ?int, sync_window_days: int, default_billable: bool, default_user_id: ?int}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(TogglPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'api_token' => $r->string('api_token'),
            'base_url' => $r->string('base_url') ?? self::DEFAULT_BASE_URL,
            'workspace_id' => $r->intOrNull('workspace_id'),
            'sync_window_days' => max(1, $r->int('sync_window_days', 30)),
            'default_billable' => $r->bool('default_billable', true),
            'default_user_id' => $r->intOrNull('default_user_id'),
        ];
    }
}
