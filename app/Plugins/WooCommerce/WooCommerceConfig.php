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

use App\Plugins\Support\PluginSettingsResolver;

/** WooCommerce-Zugang je Organisation aus den Plugin-Einstellungen. */
final class WooCommerceConfig {
    /** @return array{base_url: ?string, consumer_key: ?string, consumer_secret: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(WooCommercePlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'consumer_key' => $r->string('consumer_key', trim: true),
            'consumer_secret' => $r->string('consumer_secret', trim: true),
            'page_size' => max(1, min(100, (int) config('plugins.woocommerce.page_size', 100))),
            'max_pages' => max(1, (int) config('plugins.woocommerce.max_pages', 200)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['consumer_key'] !== null && $config['consumer_secret'] !== null;
    }
}
