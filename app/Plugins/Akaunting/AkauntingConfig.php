<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting;

use App\Plugins\Support\PluginSettingsResolver;

/** Akaunting-Zugang je Organisation aus den Plugin-Einstellungen. */
final class AkauntingConfig {
    /** @return array{base_url: ?string, email: ?string, password: ?string, company_id: int, pull_vouchers: bool, enabled: bool, page_size: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(AkauntingPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'email' => $r->string('email', trim: true),
            'password' => $r->string('password'),
            'company_id' => max(1, $r->int('company_id', 1)),
            'pull_vouchers' => $r->bool('pull_vouchers', false),
            'enabled' => $r->enabled(),
            'page_size' => max(1, min(500, (int) config('plugins.akaunting.page_size', 100))),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['email'] !== null && $config['password'] !== null;
    }
}
