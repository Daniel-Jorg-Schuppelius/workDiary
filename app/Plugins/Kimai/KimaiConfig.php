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

use App\Models\{Organization, PluginSetting};

/**
 * Effektive Kimai-Konfiguration (CSV + API): plugin_settings der gebundenen
 * Organisation, sonst `config('plugins.kimai.*')` als Fallback. Analog
 * {@see \App\Plugins\Toggl\TogglConfig}.
 *
 * @phpstan-type KimaiSettings array{enabled: bool, default_billable: bool, default_user_id: ?int, base_url: ?string, api_token: ?string, api_all_users: bool, sync_window_days: int, default_activity_id: ?int, export_enabled: bool}
 */
class KimaiConfig {
    /**
     * @return array{enabled: bool, default_billable: bool, default_user_id: ?int, base_url: ?string, api_token: ?string, api_all_users: bool, sync_window_days: int, default_activity_id: ?int, export_enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $enabled = (bool) config('plugins.kimai.enabled', false);
        $defaultBillable = (bool) config('plugins.kimai.default_billable', true);
        $defaultUserId = self::intOrNull(config('plugins.kimai.default_user_id'));
        $baseUrl = self::stringOrNull(config('plugins.kimai.base_url'));
        $apiToken = self::stringOrNull(config('plugins.kimai.api_token'));
        $apiAllUsers = (bool) config('plugins.kimai.api_all_users', true);
        $syncWindowDays = (int) config('plugins.kimai.sync_window_days', 30);
        $defaultActivityId = self::intOrNull(config('plugins.kimai.default_activity_id'));
        $exportEnabled = (bool) config('plugins.kimai.export_enabled', false);

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', KimaiPlugin::ID)
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
                $baseUrl = self::stringOrNull($s['base_url'] ?? null) ?? $baseUrl;
                $apiToken = self::stringOrNull($s['api_token'] ?? null) ?? $apiToken;
                if (isset($s['api_all_users'])) {
                    $apiAllUsers = (bool) $s['api_all_users'];
                }
                if (self::intOrNull($s['sync_window_days'] ?? null) !== null) {
                    $syncWindowDays = (int) $s['sync_window_days'];
                }
                $defaultActivityId = self::intOrNull($s['default_activity_id'] ?? null) ?? $defaultActivityId;
                if (isset($s['export_enabled'])) {
                    $exportEnabled = (bool) $s['export_enabled'];
                }
            }
        }

        return [
            'enabled' => $enabled,
            'default_billable' => $defaultBillable,
            'default_user_id' => $defaultUserId,
            'base_url' => $baseUrl,
            'api_token' => $apiToken,
            'api_all_users' => $apiAllUsers,
            'sync_window_days' => max(1, $syncWindowDays),
            'default_activity_id' => $defaultActivityId,
            'export_enabled' => $exportEnabled,
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
