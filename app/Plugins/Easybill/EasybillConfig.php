<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive easybill-Konfiguration je Organisation (Muster SevDeskConfig):
 * plugin_settings vor config('plugins.easybill.*'). Leere Strings zählen als
 * „nicht gesetzt". Das Requests/Minute-Limit ist tarifabhängig und wird als
 * Request-Intervall an den Client gegeben.
 */
class EasybillConfig {
    /**
     * @return array{api_key: ?string, base_url: string, default_vat_rate: float, rate_limit_per_minute: int, einvoice_format: ?string, pull_documents: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(EasybillPlugin::ID, $organizationId);

        return [
            'api_key' => $r->string('api_key'),
            'base_url' => rtrim($r->string('base_url') ?? 'https://api.easybill.de/rest/v1', '/'),
            'default_vat_rate' => $r->float('default_vat_rate', 19.0),
            'rate_limit_per_minute' => max(1, min(60, $r->int('rate_limit_per_minute', 10))),
            'einvoice_format' => $r->string('einvoice_format'),
            'pull_documents' => $r->bool('pull_documents', true),
            'enabled' => $r->enabled(),
        ];
    }
}
