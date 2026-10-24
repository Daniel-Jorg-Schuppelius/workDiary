<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly;

use App\Plugins\Support\PluginSettingsResolver;

/** Firefly-III-Zugang je Organisation aus den Plugin-Einstellungen. */
final class FireflyConfig {
    /** @return array{base_url: ?string, access_token: ?string, window_days: int, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(FireflyPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'access_token' => $r->string('access_token', trim: true),
            'window_days' => max(1, min(365, $r->int('window_days', 60))),
            'page_size' => max(1, min(500, (int) config('plugins.firefly.page_size', 100))),
            'max_pages' => max(1, (int) config('plugins.firefly.max_pages', 100)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['access_token'] !== null;
    }
}
