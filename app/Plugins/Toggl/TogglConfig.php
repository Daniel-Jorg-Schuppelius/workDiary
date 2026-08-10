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
     * @return array{enabled: bool, api_token: ?string, base_url: string, workspace_id: ?int, writeback: bool, sync_window_days: int, default_billable: bool, default_user_id: ?int, single_user_mode: bool, export_enabled: bool}
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
            // Einbenutzer-Modus (MVP-509): NUR wenn ausdrücklich gewählt, dürfen
            // Einträge ohne auflösbares Benutzersignal auf den Standard-Benutzer
            // gebucht werden — sonst entsteht ein offener Zuordnungsfall.
            'single_user_mode' => $r->bool('single_user_mode', false),
            // Rückrichtung (MVP-437): lokale Korrekturen an importierten Zeiten
            // nach Toggl zurückschreiben. Bewusst standardmäßig aus — sie ändert
            // Daten im Fremdsystem.
            'writeback' => $r->bool('writeback', false),
            // Spiegelung workDiary → Toggl (lokal erfasste Zeiten anlegen).
            // Bewusst standardmäßig aus — sie erzeugt Daten im Fremdsystem.
            'export_enabled' => $r->bool('export_enabled', false),
        ];
    }
}
