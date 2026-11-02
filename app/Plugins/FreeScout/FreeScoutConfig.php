<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout;

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};

/** FreeScout-Zugang je Organisation aus den Plugin-Einstellungen. */
final class FreeScoutConfig {
    /** @return array{base_url: ?string, api_key: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(FreeScoutPlugin::ID, $organizationId);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'api_key' => $r->string('api_key', trim: true),
            'page_size' => SelfHostedConfig::pageSize(FreeScoutPlugin::ID, 50, cap: 100),
            'max_pages' => SelfHostedConfig::maxPages(FreeScoutPlugin::ID, 100),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_key'] !== null;
    }
}
