<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StripeConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Stripe;

use App\Plugins\Support\PluginSettingsResolver;

class StripeConfig {
    /** @return array{enabled: bool, secret_key: string, api_base: string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(StripePlugin::ID, $organizationId);

        return [
            'enabled' => $r->enabled(),
            'secret_key' => (string) $r->string('secret_key', trim: true),
            'api_base' => rtrim((string) $r->string('api_base', 'https://api.stripe.com', true), '/'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        return self::resolve($organizationId)['secret_key'] !== '';
    }
}
