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

use App\Models\{Organization, PluginSetting};

/**
 * Liefert die effektive OpenProject-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf. Reihenfolge analog {@see \App\Plugins\Toggl\TogglConfig}:
 *   1. plugin_settings (verschlüsselt) der gebundenen Organisation
 *   2. config('plugins.openproject.*') als Fallback (ENV / config-only)
 */
class OpenProjectConfig {
    /**
     * @return array{enabled: bool, api_token: ?string, base_url: ?string, sync_window_days: int, default_billable: bool, default_user_id: ?int, default_activity_id: ?int, create_missing_projects: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $enabled = (bool) config('plugins.openproject.enabled', false);
        $apiToken = self::stringOrNull(config('plugins.openproject.api_token'));
        $baseUrl = self::stringOrNull(config('plugins.openproject.base_url'));
        $syncWindowDays = (int) config('plugins.openproject.sync_window_days', 30);
        $defaultBillable = (bool) config('plugins.openproject.default_billable', true);
        $defaultUserId = self::intOrNull(config('plugins.openproject.default_user_id'));
        $defaultActivityId = self::intOrNull(config('plugins.openproject.default_activity_id'));
        $createMissingProjects = (bool) config('plugins.openproject.create_missing_projects', false);

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', OpenProjectPlugin::ID)
                ->first();

            if ($row !== null) {
                $enabled = (bool) $row->enabled;
                $s = $row->settings ?? [];

                $apiToken = self::stringOrNull($s['api_token'] ?? null) ?? $apiToken;
                if (! empty($s['base_url'])) {
                    $baseUrl = (string) $s['base_url'];
                }
                if (isset($s['sync_window_days'])) {
                    $syncWindowDays = (int) $s['sync_window_days'];
                }
                if (isset($s['default_billable'])) {
                    $defaultBillable = (bool) $s['default_billable'];
                }
                if (self::intOrNull($s['default_user_id'] ?? null) !== null) {
                    $defaultUserId = self::intOrNull($s['default_user_id']);
                }
                if (self::intOrNull($s['default_activity_id'] ?? null) !== null) {
                    $defaultActivityId = self::intOrNull($s['default_activity_id']);
                }
                if (isset($s['create_missing_projects'])) {
                    $createMissingProjects = (bool) $s['create_missing_projects'];
                }
            }
        }

        return [
            'enabled' => $enabled,
            'api_token' => $apiToken,
            'base_url' => self::normalizeBaseUrl($baseUrl),
            'sync_window_days' => max(1, $syncWindowDays),
            'default_billable' => $defaultBillable,
            'default_user_id' => $defaultUserId,
            'default_activity_id' => $defaultActivityId,
            'create_missing_projects' => $createMissingProjects,
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

    private static function stringOrNull(mixed $value): ?string {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    private static function intOrNull(mixed $value): ?int {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function boundOrganizationId(): ?int {
        if (! app()->bound('currentOrganization')) {
            return null;
        }
        $org = app('currentOrganization');

        return $org instanceof Organization ? (int) $org->id : null;
    }
}
