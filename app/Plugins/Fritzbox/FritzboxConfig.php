<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Fritzbox;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive FritzBox-Konfiguration: plugin_settings der gebundenen Organisation
 * vor `config('plugins.fritzbox.*')` — Lookup/Cast im {@see PluginSettingsResolver}.
 *
 * @phpstan-type FritzboxSettings array{enabled: bool, default_billable: bool, default_user_id: ?int, min_call_minutes: int, call_lead_minutes: int, own_number_allowlist: list<string>, type3_outgoing: bool, stamp_in_line: string, stamp_out_line: string, stamp_toggle_line: string}
 */
class FritzboxConfig {
    /**
     * @return array{enabled: bool, default_billable: bool, default_user_id: ?int, min_call_minutes: int, call_lead_minutes: int, own_number_allowlist: list<string>, type3_outgoing: bool, stamp_in_line: string, stamp_out_line: string, stamp_toggle_line: string}
     */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(FritzboxPlugin::ID, $organizationId);

        $allowlist = array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) $r->string('own_number_allowlist', '')),
        ), static fn (string $number): bool => $number !== ''));

        return [
            'enabled' => $r->enabled(),
            'default_billable' => $r->bool('default_billable', true),
            'default_user_id' => $r->intOrNull('default_user_id'),
            'min_call_minutes' => max(0, $r->int('min_call_minutes', 2)),
            'call_lead_minutes' => max(0, $r->int('call_lead_minutes', 15)),
            'own_number_allowlist' => $allowlist,
            'type3_outgoing' => $r->bool('type3_outgoing', false),
            'stamp_in_line' => (string) $r->string('stamp_in_line', ''),
            'stamp_out_line' => (string) $r->string('stamp_out_line', ''),
            'stamp_toggle_line' => (string) $r->string('stamp_toggle_line', ''),
        ];
    }
}
