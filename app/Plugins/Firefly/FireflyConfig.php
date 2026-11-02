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

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};

/** Firefly-III-Zugang je Organisation aus den Plugin-Einstellungen. */
final class FireflyConfig {
    /** @return array{base_url: ?string, access_token: ?string, window_days: int, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(FireflyPlugin::ID, $organizationId);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'access_token' => $r->string('access_token', trim: true),
            'window_days' => max(1, min(365, $r->int('window_days', 60))),
            'page_size' => SelfHostedConfig::pageSize(FireflyPlugin::ID, 100, cap: 500),
            'max_pages' => SelfHostedConfig::maxPages(FireflyPlugin::ID, 100),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['access_token'] !== null;
    }
}
