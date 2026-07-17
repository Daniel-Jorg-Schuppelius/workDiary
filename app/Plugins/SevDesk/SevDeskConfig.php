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

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive sevDesk-Konfiguration je Organisation (Muster LexofficeConfig):
 * plugin_settings vor config('plugins.sevdesk.*') — Lookup/Cast im
 * {@see PluginSettingsResolver} (C10). Leere Strings zählen als „nicht
 * gesetzt" (leere encrypted-Strings ⇒ null). Die ID-Stammwerte (tax_rule
 * u. a.) bleiben bewusst installationsweit (config-only).
 */
class SevDeskConfig {
    /**
     * @return array{api_key: ?string, base_url: string, default_vat_rate: float, tax_rule_id: int, contact_category_id: int, unity_piece_id: int, unity_hour_id: int, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(SevDeskPlugin::ID, $organizationId);

        return [
            'api_key' => $r->string('api_key'),
            'base_url' => rtrim($r->string('base_url') ?? 'https://my.sevdesk.de/api/v1', '/'),
            'default_vat_rate' => $r->float('default_vat_rate', 19.0),
            'tax_rule_id' => (int) config('plugins.sevdesk.tax_rule_id', 1),
            'contact_category_id' => (int) config('plugins.sevdesk.contact_category_id', 3),
            'unity_piece_id' => (int) config('plugins.sevdesk.unity_piece_id', 1),
            'unity_hour_id' => (int) config('plugins.sevdesk.unity_hour_id', 9),
            'enabled' => $r->enabled(),
        ];
    }
}
