<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\RemoteSupport;

use App\Models\{Organization, PluginSetting};

/**
 * Liefert die effektive Fernwartungs-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf. Reihenfolge analog {@see \App\Plugins\Lexoffice\LexofficeConfig}:
 *   1. plugin_settings (verschlüsselt) der gebundenen Organisation
 *   2. config('plugins.remote-support.*') als Fallback (ENV / config-only)
 *
 * @phpstan-type ProviderConfig array{enabled: bool, license_id: ?string, api_key: ?string, base_url: string}
 */
class RemoteSupportConfig {
    /**
     * @return array{enabled: bool, sync_window_days: int, default_billable: bool, default_user_id: ?int, anydesk: ProviderConfig, teamviewer: ProviderConfig}
     */
    public static function resolve(?int $organizationId = null): array {
        $enabled = (bool) config('plugins.remote-support.enabled', false);
        $syncWindowDays = (int) config('plugins.remote-support.sync_window_days', 2);
        $defaultBillable = (bool) config('plugins.remote-support.default_billable', true);
        $defaultUserId = self::intOrNull(config('plugins.remote-support.default_user_id'));

        $anydesk = [
            'enabled' => (bool) config('plugins.remote-support.anydesk.enabled', false),
            'license_id' => self::stringOrNull(config('plugins.remote-support.anydesk.license_id')),
            'api_key' => self::stringOrNull(config('plugins.remote-support.anydesk.api_key')),
            'base_url' => (string) config('plugins.remote-support.anydesk.base_url', 'https://v1.api.anydesk.com'),
        ];
        $teamviewer = [
            'enabled' => (bool) config('plugins.remote-support.teamviewer.enabled', false),
            'license_id' => null,
            'api_key' => self::stringOrNull(config('plugins.remote-support.teamviewer.api_key')),
            'base_url' => (string) config('plugins.remote-support.teamviewer.base_url', 'https://webapi.teamviewer.com/api/v1'),
        ];

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', RemoteSupportPlugin::ID)
                ->first();

            if ($row !== null) {
                $enabled = (bool) $row->enabled;
                $s = $row->settings ?? [];

                if (isset($s['sync_window_days'])) {
                    $syncWindowDays = (int) $s['sync_window_days'];
                }
                if (isset($s['default_billable'])) {
                    $defaultBillable = (bool) $s['default_billable'];
                }
                if (self::intOrNull($s['default_user_id'] ?? null) !== null) {
                    $defaultUserId = self::intOrNull($s['default_user_id']);
                }

                $anydesk['enabled'] = (bool) ($s['anydesk_enabled'] ?? $anydesk['enabled']);
                $anydesk['license_id'] = self::stringOrNull($s['anydesk_license_id'] ?? null) ?? $anydesk['license_id'];
                $anydesk['api_key'] = self::stringOrNull($s['anydesk_api_key'] ?? null) ?? $anydesk['api_key'];
                if (! empty($s['anydesk_base_url'])) {
                    $anydesk['base_url'] = (string) $s['anydesk_base_url'];
                }

                $teamviewer['enabled'] = (bool) ($s['teamviewer_enabled'] ?? $teamviewer['enabled']);
                $teamviewer['api_key'] = self::stringOrNull($s['teamviewer_api_key'] ?? null) ?? $teamviewer['api_key'];
                if (! empty($s['teamviewer_base_url'])) {
                    $teamviewer['base_url'] = (string) $s['teamviewer_base_url'];
                }
            }
        }

        return [
            'enabled' => $enabled,
            'sync_window_days' => max(1, $syncWindowDays),
            'default_billable' => $defaultBillable,
            'default_user_id' => $defaultUserId,
            'anydesk' => $anydesk,
            'teamviewer' => $teamviewer,
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
