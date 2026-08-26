<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SipgateConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Sipgate;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive sipgate-Konfiguration: plugin_settings der gebundenen
 * Organisation vor `config('plugins.sipgate.*')` (C10-Muster).
 *
 * @phpstan-type SipgateSettings array{enabled: bool, token_id: ?string, token: ?string, api_base: string, sms_id: string}
 */
class SipgateConfig {
    /** @return array{enabled: bool, token_id: ?string, token: ?string, api_base: string, sms_id: string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(SipgatePlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'token_id' => $r->string('token_id', trim: true),
            'token' => $r->string('token', trim: true),
            'api_base' => rtrim((string) $r->string('api_base', 'https://api.sipgate.com/v2', true), '/'),
            'sms_id' => (string) ($r->string('sms_id', 's0', true) ?? 's0'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return (string) ($config['token_id'] ?? '') !== '' && (string) ($config['token'] ?? '') !== '';
    }
}
