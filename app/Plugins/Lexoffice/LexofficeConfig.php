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

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Liefert die effektive Plugin-Konfiguration für den aktuellen Request /
 * Konsolen-Lauf: plugin_settings der gebundenen Organisation vor
 * config('plugins.lexoffice.*') — Lookup/Cast im {@see PluginSettingsResolver}
 * (C10). Damit funktionieren bestehende Tests + .env-getriebene Setups weiter,
 * während die UI-Konfiguration pro Org Vorrang hat.
 */
class LexofficeConfig {
    /**
     * @return array{api_key: ?string, base_url: string, defaults: array<string, mixed>, match_policy: string, create_missing_local: bool, number_authority: bool, enabled: bool}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(LexofficePlugin::ID, $organizationId);

        return [
            'api_key' => $r->string('api_key'),
            'base_url' => $r->string('base_url') ?? 'https://api.lexoffice.io/v1',
            'defaults' => [
                'default_currency' => $r->string('default_currency') ?? 'EUR',
                'default_tax_type' => $r->string('default_tax_type') ?? 'net',
                'default_vat_rate' => $r->float('default_vat_rate', 19.0),
            ],
            'match_policy' => $r->string('match_policy') ?? 'manual_review',
            'create_missing_local' => $r->bool('create_missing_local', false),
            'number_authority' => $r->bool('number_authority', false),
            'enabled' => $r->enabled(),
        ];
    }
}
