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

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};

/** Taiga-Zugang je Organisation aus den Plugin-Einstellungen. */
final class TaigaConfig {
    /** @return array{base_url: ?string, username: ?string, password: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(TaigaPlugin::ID, $organizationId);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'username' => $r->string('username', trim: true),
            'password' => $r->string('password', trim: true),
            'page_size' => SelfHostedConfig::pageSize(TaigaPlugin::ID, 50, cap: 100),
            'max_pages' => SelfHostedConfig::maxPages(TaigaPlugin::ID, 100),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['username'] !== null && $config['password'] !== null;
    }
}
