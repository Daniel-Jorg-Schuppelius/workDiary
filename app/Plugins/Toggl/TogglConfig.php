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

use App\Models\{Organization, PluginSetting};

/**
 * Liefert die effektive Toggl-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf. Reihenfolge analog {@see \App\Plugins\RemoteSupport\RemoteSupportConfig}:
 *   1. plugin_settings (verschlüsselt) der gebundenen Organisation
 *   2. config('plugins.toggl.*') als Fallback (ENV / config-only)
 */
class TogglConfig {
    public const DEFAULT_BASE_URL = 'https://api.track.toggl.com/api/v9';

    /**
     * @return array{enabled: bool, api_token: ?string, base_url: string, workspace_id: ?int, sync_window_days: int, default_billable: bool, default_user_id: ?int}
     */
    public static function resolve(?int $organizationId = null): array {
        $enabled = (bool) config('plugins.toggl.enabled', false);
        $apiToken = self::stringOrNull(config('plugins.toggl.api_token'));
        $baseUrl = (string) config('plugins.toggl.base_url', self::DEFAULT_BASE_URL);
        $workspaceId = self::intOrNull(config('plugins.toggl.workspace_id'));
        $syncWindowDays = (int) config('plugins.toggl.sync_window_days', 30);
        $defaultBillable = (bool) config('plugins.toggl.default_billable', true);
        $defaultUserId = self::intOrNull(config('plugins.toggl.default_user_id'));

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', TogglPlugin::ID)
                ->first();

            if ($row !== null) {
                $enabled = (bool) $row->enabled;
                $s = $row->settings ?? [];

                $apiToken = self::stringOrNull($s['api_token'] ?? null) ?? $apiToken;
                if (! empty($s['base_url'])) {
                    $baseUrl = (string) $s['base_url'];
                }
                if (self::intOrNull($s['workspace_id'] ?? null) !== null) {
                    $workspaceId = self::intOrNull($s['workspace_id']);
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
            }
        }

        return [
            'enabled' => $enabled,
            'api_token' => $apiToken,
            'base_url' => $baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL,
            'workspace_id' => $workspaceId,
            'sync_window_days' => max(1, $syncWindowDays),
            'default_billable' => $defaultBillable,
            'default_user_id' => $defaultUserId,
        ];
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
