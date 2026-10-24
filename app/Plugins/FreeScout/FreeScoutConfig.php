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

use App\Plugins\Support\PluginSettingsResolver;

/** FreeScout-Zugang je Organisation aus den Plugin-Einstellungen. */
final class FreeScoutConfig {
    /** @return array{base_url: ?string, api_key: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(FreeScoutPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'api_key' => $r->string('api_key', trim: true),
            'page_size' => max(1, min(100, (int) config('plugins.freescout.page_size', 50))),
            'max_pages' => max(1, (int) config('plugins.freescout.max_pages', 100)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_key'] !== null;
    }
}
