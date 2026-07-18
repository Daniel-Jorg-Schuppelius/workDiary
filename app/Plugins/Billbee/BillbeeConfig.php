<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive Billbee-Konfiguration je Organisation (Muster Easybill-/BhbConfig):
 * plugin_settings vor config('plugins.billbee.*'). Leere Strings zählen als
 * „nicht gesetzt".
 */
class BillbeeConfig {
    /**
     * @return array{api_key: ?string, username: ?string, api_password: ?string, base_url: string, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(BillbeePlugin::ID, $organizationId);

        return [
            'api_key' => $r->string('api_key'),
            'username' => $r->string('username'),
            'api_password' => $r->string('api_password'),
            'base_url' => rtrim($r->string('base_url') ?? (string) config('plugins.billbee.base_url'), '/'),
            'enabled' => $r->enabled(),
        ];
    }
}
