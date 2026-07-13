<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk;

use App\Models\{Organization, PluginSetting};

/**
 * Effektive sevDesk-Konfiguration je Organisation (Muster LexofficeConfig):
 *
 *   1. plugin_settings (verschlüsselt) der Organisation
 *   2. config('plugins.sevdesk.*') als Fallback (ENV / Tests / Konsole)
 *
 * Leere Strings zählen als „nicht gesetzt" (leere encrypted-Strings ⇒ null,
 * daher konsequent `!empty()`/`?:` statt `??`).
 */
class SevDeskConfig {
    /**
     * @return array{api_key: ?string, base_url: string, default_vat_rate: float, tax_rule_id: int, contact_category_id: int, unity_piece_id: int, unity_hour_id: int, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $apiKey = null;
        $baseUrl = (string) config('plugins.sevdesk.base_url', 'https://my.sevdesk.de/api/v1');
        $vatRate = (float) config('plugins.sevdesk.default_vat_rate', 19.0);
        $enabled = (bool) config('plugins.sevdesk.enabled', false);

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', SevDeskPlugin::ID)
                ->first();

            if ($row !== null) {
                $enabled = (bool) $row->enabled;
                $settings = $row->settings ?? [];
                if (! empty($settings['api_key'])) {
                    $apiKey = (string) $settings['api_key'];
                }
                if (! empty($settings['base_url'])) {
                    $baseUrl = (string) $settings['base_url'];
                }
                if (! empty($settings['default_vat_rate'])) {
                    $vatRate = (float) $settings['default_vat_rate'];
                }
            }
        }

        if ($apiKey === null) {
            $envKey = (string) (config('plugins.sevdesk.api_key') ?: '');
            $apiKey = $envKey !== '' ? $envKey : null;
        }

        return [
            'api_key' => $apiKey,
            'base_url' => rtrim($baseUrl, '/'),
            'default_vat_rate' => $vatRate,
            'tax_rule_id' => (int) config('plugins.sevdesk.tax_rule_id', 1),
            'contact_category_id' => (int) config('plugins.sevdesk.contact_category_id', 3),
            'unity_piece_id' => (int) config('plugins.sevdesk.unity_piece_id', 1),
            'unity_hour_id' => (int) config('plugins.sevdesk.unity_hour_id', 9),
            'enabled' => $enabled,
        ];
    }

    private static function boundOrganizationId(): ?int {
        if (! app()->bound('currentOrganization')) {
            return null;
        }

        $organization = app('currentOrganization');

        return $organization instanceof Organization ? (int) $organization->id : null;
    }
}
