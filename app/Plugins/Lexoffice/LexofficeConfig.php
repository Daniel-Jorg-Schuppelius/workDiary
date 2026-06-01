<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use App\Models\{Organization, PluginSetting};

/**
 * Liefert die effektive Plugin-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf. Reihenfolge:
 *   1. plugin_settings (verschlüsselt) der gebundenen Organisation
 *   2. config('plugins.lexoffice.*') als Fallback (ENV / config-only)
 *
 * Damit funktionieren bestehende Tests + .env-getriebene Setups weiter,
 * während die UI-Konfiguration pro Org Vorrang hat.
 */
class LexofficeConfig {
    /**
     * @return array{api_key: ?string, base_url: string, defaults: array<string, mixed>, match_policy: string, create_missing_local: bool, number_authority: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $apiKey = null;
        $baseUrl = (string) config('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');
        $defaults = [
            'default_currency' => (string) config('plugins.lexoffice.default_currency', 'EUR'),
            'default_tax_type' => (string) config('plugins.lexoffice.default_tax_type', 'net'),
            'default_vat_rate' => (float) config('plugins.lexoffice.default_vat_rate', 19.0),
        ];
        $matchPolicy = (string) config('plugins.lexoffice.match_policy', 'manual_review');
        $createMissing = (bool) config('plugins.lexoffice.create_missing_local', false);
        $numberAuthority = (bool) config('plugins.lexoffice.number_authority', false);
        $enabled = (bool) config('plugins.lexoffice.enabled', false);

        $organizationId ??= self::boundOrganizationId();

        if ($organizationId !== null) {
            $row = PluginSetting::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('plugin_id', LexofficePlugin::ID)
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
                if (! empty($settings['default_currency'])) {
                    $defaults['default_currency'] = (string) $settings['default_currency'];
                }
                if (! empty($settings['default_tax_type'])) {
                    $defaults['default_tax_type'] = (string) $settings['default_tax_type'];
                }
                if (isset($settings['default_vat_rate'])) {
                    $defaults['default_vat_rate'] = (float) $settings['default_vat_rate'];
                }
                if (! empty($settings['match_policy'])) {
                    $matchPolicy = (string) $settings['match_policy'];
                }
                if (isset($settings['create_missing_local'])) {
                    $createMissing = (bool) $settings['create_missing_local'];
                }
                if (isset($settings['number_authority'])) {
                    $numberAuthority = (bool) $settings['number_authority'];
                }
            }
        }

        if ($apiKey === null) {
            $env = config('plugins.lexoffice.api_key');
            if (is_string($env) && $env !== '') {
                $apiKey = $env;
            }
        }

        return [
            'api_key' => $apiKey,
            'base_url' => $baseUrl,
            'defaults' => $defaults,
            'match_policy' => $matchPolicy,
            'create_missing_local' => $createMissing,
            'number_authority' => $numberAuthority,
            'enabled' => $enabled,
        ];
    }

    private static function boundOrganizationId(): ?int {
        if (! app()->bound('currentOrganization')) {
            return null;
        }
        $org = app('currentOrganization');

        return $org instanceof Organization ? (int) $org->id : null;
    }
}
