<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi;

use App\Plugins\Support\PluginSettingsResolver;

/** GLPI-Zugang je Organisation aus den Plugin-Einstellungen. */
final class GlpiConfig {
    /** @return array{base_url: ?string, app_token: ?string, user_token: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(GlpiPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'app_token' => $r->string('app_token', trim: true),
            'user_token' => $r->string('user_token', trim: true),
            'page_size' => max(1, min(1000, (int) config('plugins.glpi.page_size', 200))),
            'max_pages' => max(1, (int) config('plugins.glpi.max_pages', 100)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['user_token'] !== null;
    }
}
