<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime;

use App\Plugins\Support\PluginSettingsResolver;

/** Leantime-Zugang je Organisation aus den Plugin-Einstellungen. */
final class LeantimeConfig {
    /** @return array{base_url: ?string, api_key: ?string} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(LeantimePlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'api_key' => $r->string('api_key', trim: true),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_key'] !== null;
    }
}
