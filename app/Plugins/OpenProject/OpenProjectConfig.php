<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\OpenProject;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Liefert die effektive OpenProject-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf: plugin_settings der gebundenen Organisation vor
 * config('plugins.openproject.*') — Lookup/Cast im {@see PluginSettingsResolver} (C10).
 */
class OpenProjectConfig {
    /**
     * @return array{enabled: bool, api_token: ?string, base_url: ?string, sync_window_days: int, default_billable: bool, default_user_id: ?int, default_activity_id: ?int, create_missing_projects: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(OpenProjectPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'api_token' => $r->string('api_token'),
            'base_url' => self::normalizeBaseUrl($r->string('base_url')),
            'sync_window_days' => max(1, $r->int('sync_window_days', 30)),
            'default_billable' => $r->bool('default_billable', true),
            'default_user_id' => $r->intOrNull('default_user_id'),
            'default_activity_id' => $r->intOrNull('default_activity_id'),
            'create_missing_projects' => $r->bool('create_missing_projects', false),
        ];
    }

    /**
     * Normalisiert die Instanz-URL auf die API-Wurzel `…/api/v3` (ohne
     * abschließenden Slash). Akzeptiert sowohl die blanke Host-URL
     * (`https://op.example.com`) als auch eine bereits vollständige API-URL.
     */
    public static function normalizeBaseUrl(?string $baseUrl): ?string {
        $baseUrl = $baseUrl !== null ? rtrim(trim($baseUrl), '/') : '';
        if ($baseUrl === '') {
            return null;
        }

        return str_ends_with($baseUrl, '/api/v3') ? $baseUrl : $baseUrl . '/api/v3';
    }
}
