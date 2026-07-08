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

use App\Models\{Organization, PluginSetting};

/**
 * Effektive Clockify-Konfiguration (CSV + API): plugin_settings der gebundenen
 * Organisation, sonst `config('plugins.clockify.*')` als Fallback. Analog
 * {@see \App\Plugins\Kimai\KimaiConfig}.
 *
 * @phpstan-type ClockifySettings array{enabled: bool, default_billable: bool, default_user_id: ?int, api_key: ?string, workspace_id: ?string, base_url: string, reports_base_url: string, sync_window_days: int}
 */
class ClockifyConfig {
    public const DEFAULT_BASE_URL = 'https://api.clockify.me/api';

    public const DEFAULT_REPORTS_BASE_URL = 'https://reports.api.clockify.me/v1';

    /**
     * @return array{enabled: bool, default_billable: bool, default_user_id: ?int, api_key: ?string, workspace_id: ?string, base_url: string, reports_base_url: string, sync_window_days: int}
     */
    public static function resolve(?int $organizationId = null): array {
        $enabled = (bool) config('plugins.clockify.enabled', false);
        $defaultBillable = (bool) config('plugins.clockify.default_billable', true);
        $defaultUserId = self::intOrNull(config('plugins.clockify.default_user_id'));
        $apiKey = self::stringOrNull(config('plugins.clockify.api_key'));
        $workspaceId = self::stringOrNull(config('plugins.clockify.workspace_id'));
        $baseUrl = self::stringOrNull(config('plugins.clockify.base_url')) ?? self::DEFAULT_BASE_URL;
        $reportsBaseUrl = self::stringOrNull(config('plugins.clockify.reports_base_url')) ?? self::DEFAULT_REPORTS_BASE_URL;
        $syncWindowDays = (int) config('plugins.clockify.sync_window_days', 30);

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', ClockifyPlugin::ID)
                ->first();

            if ($row !== null) {
                $enabled = (bool) $row->enabled;
                $s = $row->settings ?? [];
                if (isset($s['default_billable'])) {
                    $defaultBillable = (bool) $s['default_billable'];
                }
                if (self::intOrNull($s['default_user_id'] ?? null) !== null) {
                    $defaultUserId = self::intOrNull($s['default_user_id']);
                }
                $apiKey = self::stringOrNull($s['api_key'] ?? null) ?? $apiKey;
                $workspaceId = self::stringOrNull($s['workspace_id'] ?? null) ?? $workspaceId;
                $baseUrl = self::stringOrNull($s['base_url'] ?? null) ?? $baseUrl;
                $reportsBaseUrl = self::stringOrNull($s['reports_base_url'] ?? null) ?? $reportsBaseUrl;
                if (self::intOrNull($s['sync_window_days'] ?? null) !== null) {
                    $syncWindowDays = (int) $s['sync_window_days'];
                }
            }
        }

        return [
            'enabled' => $enabled,
            'default_billable' => $defaultBillable,
            'default_user_id' => $defaultUserId,
            'api_key' => $apiKey,
            'workspace_id' => $workspaceId,
            'base_url' => $baseUrl,
            'reports_base_url' => $reportsBaseUrl,
            'sync_window_days' => max(1, $syncWindowDays),
        ];
    }

    private static function intOrNull(mixed $value): ?int {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function boundOrganizationId(): ?int {
        if (! app()->bound('currentOrganization')) {
            return null;
        }
        $org = app('currentOrganization');

        return $org instanceof Organization ? (int) $org->id : null;
    }
}
