<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceNinjaConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoiceNinja;

use App\Plugins\Support\PluginSettingsResolver;

/** Invoice-Ninja-Zugang je Organisation aus den Plugin-Einstellungen (gehostet: invoicing.co). */
final class InvoiceNinjaConfig {
    public const HOSTED_URL = 'https://invoicing.co';

    /** @return array{base_url: string, api_token: ?string, pull_vouchers: bool, enabled: bool, page_size: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(InvoiceNinjaPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => rtrim($baseUrl ?? self::HOSTED_URL, '/'),
            'api_token' => $r->string('api_token', trim: true),
            'pull_vouchers' => $r->bool('pull_vouchers', false),
            'enabled' => $r->enabled(),
            'page_size' => max(1, min(500, (int) config('plugins.invoiceninja.page_size', 100))),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        return self::resolve($organizationId)['api_token'] !== null;
    }
}
