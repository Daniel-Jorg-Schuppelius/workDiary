<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevenIoConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevenIo;

use App\Plugins\Support\PluginSettingsResolver;

/**
 * Effektive seven.io-Konfiguration: plugin_settings der gebundenen
 * Organisation vor `config('plugins.sevenio.*')` (C10-Muster).
 *
 * Der API-Key kommt bewusst über {@see PluginSettingsResolver::string()} —
 * also inklusive ENV-Fallback für Single-Tenant-Installationen; alles andere
 * ist Org-Konfiguration.
 *
 * @phpstan-type SevenIoSettings array{enabled: bool, api_key: ?string, api_base: string, from: ?string}
 */
class SevenIoConfig {
    /** @return array{enabled: bool, api_key: ?string, api_base: string, from: ?string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(SevenIoPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'api_key' => $r->string('api_key', trim: true),
            'api_base' => rtrim((string) $r->string('api_base', 'https://gateway.seven.io/api', true), '/'),
            'from' => $r->string('from', trim: true),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        return (string) (self::resolve($organizationId)['api_key'] ?? '') !== '';
    }
}
