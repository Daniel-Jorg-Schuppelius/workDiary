<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane;

use App\Plugins\Support\PluginSettingsResolver;

/** Plane-Zugang je Organisation aus den Plugin-Einstellungen. */
final class PlaneConfig {
    /** @return array{base_url: ?string, workspace_slug: ?string, api_key: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(PlanePlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'workspace_slug' => $r->string('workspace_slug', trim: true),
            'api_key' => $r->string('api_key', trim: true),
            'page_size' => max(1, min(100, (int) config('plugins.plane.page_size', 50))),
            'max_pages' => max(1, (int) config('plugins.plane.max_pages', 100)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['workspace_slug'] !== null && $config['api_key'] !== null;
    }
}
