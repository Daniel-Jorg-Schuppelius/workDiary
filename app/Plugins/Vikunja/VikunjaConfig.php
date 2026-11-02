<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja;

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};

/** Vikunja-Zugang je Organisation aus den Plugin-Einstellungen. */
final class VikunjaConfig {
    /** @return array{base_url: ?string, api_token: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(VikunjaPlugin::ID, $organizationId);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'api_token' => $r->string('api_token', trim: true),
            'page_size' => SelfHostedConfig::pageSize(VikunjaPlugin::ID, 50, cap: 100),
            'max_pages' => SelfHostedConfig::maxPages(VikunjaPlugin::ID, 100),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_token'] !== null;
    }
}
