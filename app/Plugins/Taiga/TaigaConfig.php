<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga;

use App\Plugins\Support\PluginSettingsResolver;

/** Taiga-Zugang je Organisation aus den Plugin-Einstellungen. */
final class TaigaConfig {
    /** @return array{base_url: ?string, username: ?string, password: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(TaigaPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'username' => $r->string('username', trim: true),
            'password' => $r->string('password', trim: true),
            'page_size' => max(1, min(100, (int) config('plugins.taiga.page_size', 50))),
            'max_pages' => max(1, (int) config('plugins.taiga.max_pages', 100)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['username'] !== null && $config['password'] !== null;
    }
}
