<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SumUpConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SumUp;

use App\Plugins\Support\PluginSettingsResolver;

class SumUpConfig {
    /** @return array{enabled: bool, api_key: string, merchant_code: string, api_base: string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(SumUpPlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'api_key' => (string) $r->string('api_key', trim: true),
            'merchant_code' => (string) $r->string('merchant_code', trim: true),
            'api_base' => rtrim((string) $r->string('api_base', 'https://api.sumup.com', true), '/'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['api_key'] !== '' && $config['merchant_code'] !== '';
    }
}
