<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine;

use App\Plugins\Support\PluginSettingsResolver;

/** Redmine-Zugang und Zeitimport-Vorgaben je Organisation aus den Plugin-Einstellungen. */
final class RedmineConfig {
    /** @return array{base_url: ?string, api_key: ?string, default_billable: bool, time_window_days: int, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(RedminePlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'api_key' => $r->string('api_key', trim: true),
            'default_billable' => $r->bool('default_billable', true),
            'time_window_days' => max(1, min(365, $r->int('time_window_days', (int) config('plugins.redmine.time_window_days', 30)))),
            'page_size' => max(1, min(100, (int) config('plugins.redmine.page_size', 100))),
            'max_pages' => max(1, (int) config('plugins.redmine.max_pages', 100)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_key'] !== null;
    }
}
