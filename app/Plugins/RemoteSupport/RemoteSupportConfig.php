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

use App\Models\Platform\PluginSetting;
use App\Plugins\RemoteSupport\Providers\RemoteProviders;
use App\Plugins\Support\PluginOrgContext;
use CommonToolkit\Helper\Data\{NumberHelper, StringHelper};

/**
 * Liefert die effektive Fernwartungs-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf. Reihenfolge analog {@see \App\Plugins\Lexoffice\LexofficeConfig}:
 *   1. plugin_settings (verschlüsselt) der gebundenen Organisation
 *   2. config('plugins.remote-support.*') als Fallback (ENV / config-only)
 *
 * Je Anbieter des Registers ({@see RemoteProviders}) entsteht ein Teil-Array
 * `<id>` mit `enabled` und den Schlüsseln seiner Definition: Org-Einstellung
 * `<id>_<key>` vor `plugins.remote-support.<id>.<key>` vor der Vorgabe.
 *
 * @phpstan-type ProviderConfig array<string, mixed>
 */
class RemoteSupportConfig {
    /**
     * @return array<string, mixed>  `enabled`, `sync_window_days`, `default_billable`, `default_user_id` und je Anbieter ein ProviderConfig
     */
    public static function resolve(?int $organizationId = null): array {
        $enabled = (bool) config('plugins.remote-support.enabled', false);
        $syncWindowDays = (int) config('plugins.remote-support.sync_window_days', 2);
        $defaultBillable = (bool) config('plugins.remote-support.default_billable', true);
        $defaultUserId = NumberHelper::toIntOrNull(config('plugins.remote-support.default_user_id'));

        $organizationId ??= PluginOrgContext::currentId();

        $s = [];
        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', RemoteSupportPlugin::ID)
                ->first();

            if ($row !== null) {
                $enabled = (bool) $row->enabled;
                $s = is_array($row->settings) ? $row->settings : [];

                if (isset($s['sync_window_days'])) {
                    $syncWindowDays = (int) $s['sync_window_days'];
                }
                if (isset($s['default_billable'])) {
                    $defaultBillable = (bool) $s['default_billable'];
                }
                if (NumberHelper::toIntOrNull($s['default_user_id'] ?? null) !== null) {
                    $defaultUserId = NumberHelper::toIntOrNull($s['default_user_id']);
                }
            }
        }

        $config = [
            'enabled' => $enabled,
            'sync_window_days' => max(1, $syncWindowDays),
            'default_billable' => $defaultBillable,
            'default_user_id' => $defaultUserId,
        ];

        foreach (RemoteProviders::all() as $id => $definition) {
            $prefix = 'plugins.remote-support.' . $id . '.';
            $provider = [
                'enabled' => (bool) ($s[$id . '_enabled'] ?? config($prefix . 'enabled', false)),
            ];
            foreach ($definition->configFields as $field) {
                $own = $s[$id . '_' . $field['key']] ?? null;
                $fallback = config($prefix . $field['key']);
                $provider[$field['key']] = match ($field['cast']) {
                    'bool' => $own !== null ? (bool) $own : (bool) ($fallback ?? $field['default']),
                    'int' => NumberHelper::toIntOrNull($own) ?? NumberHelper::toIntOrNull($fallback) ?? $field['default'],
                    default => StringHelper::trimToNull(is_scalar($own) ? (string) $own : null)
                        ?? StringHelper::trimToNull(is_scalar($fallback) ? (string) $fallback : null)
                        ?? $field['default'],
                };
            }
            $config[$id] = $provider;
        }

        return $config;
    }
}
