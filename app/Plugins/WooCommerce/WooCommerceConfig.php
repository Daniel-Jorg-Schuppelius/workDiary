<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce;

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};

/** WooCommerce-Zugang je Organisation aus den Plugin-Einstellungen. */
final class WooCommerceConfig {
    /** @return array{base_url: ?string, consumer_key: ?string, consumer_secret: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(WooCommercePlugin::ID, $organizationId);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'consumer_key' => $r->string('consumer_key', trim: true),
            'consumer_secret' => $r->string('consumer_secret', trim: true),
            'page_size' => SelfHostedConfig::pageSize(WooCommercePlugin::ID, 100, cap: 100),
            'max_pages' => SelfHostedConfig::maxPages(WooCommercePlugin::ID, 200),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['consumer_key'] !== null && $config['consumer_secret'] !== null;
    }
}
