<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware;

use App\Plugins\Support\PluginSettingsResolver;

/** Shopware-Zugang je Organisation aus den Plugin-Einstellungen. */
final class ShopwareConfig {
    /** @return array{base_url: ?string, client_id: ?string, client_secret: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(ShopwarePlugin::ID, $organizationId);
        $baseUrl = $r->string('base_url', trim: true);

        return [
            'base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
            'client_id' => $r->string('client_id', trim: true),
            'client_secret' => $r->string('client_secret', trim: true),
            'page_size' => max(1, min(500, (int) config('plugins.shopware.page_size', 100))),
            'max_pages' => max(1, (int) config('plugins.shopware.max_pages', 200)),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['client_id'] !== null && $config['client_secret'] !== null;
    }
}
