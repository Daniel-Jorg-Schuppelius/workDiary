<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard;

use App\Plugins\Support\PluginSettingsResolver;

/** Kanboard-Zugang je Organisation aus den Plugin-Einstellungen. */
final class KanboardConfig {
    /** @return array{base_url: ?string, username: ?string, api_token: ?string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(KanboardPlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'username' => $r->string('username', trim: true),
            'api_token' => $r->string('api_token', trim: true),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_token'] !== null;
    }
}
