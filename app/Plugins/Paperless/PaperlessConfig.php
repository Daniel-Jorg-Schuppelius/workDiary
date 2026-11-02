<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Paperless;

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};

/** Paperless-ngx-Zugang je Organisation aus den Plugin-Einstellungen (selbst gehostet, keine Vorgabe-Instanz). */
final class PaperlessConfig {
    /** @return array{base_url: ?string, api_token: ?string, page_size: int, max_id_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(PaperlessPlugin::ID, $organizationId);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'api_token' => $r->string('api_token', trim: true),
            'page_size' => SelfHostedConfig::pageSize(PaperlessPlugin::ID, 25, cap: 100),
            'max_id_pages' => SelfHostedConfig::maxPages(PaperlessPlugin::ID, 400, 'max_id_pages'),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_token'] !== null;
    }
}
