<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopConfig.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop;

use App\Plugins\Support\{PluginSettingsResolver, SelfHostedConfig};
use App\Support\Tz;

/** PrestaShop-Zugang je Organisation aus den Plugin-Einstellungen. */
final class PrestaShopConfig {
    /** @return array{base_url: ?string, api_key: ?string, shop_timezone: ?string, page_size: int, max_pages: int} */
    public static function resolve(?int $organizationId = null): array {
        $r = PluginSettingsResolver::for(PrestaShopPlugin::ID, $organizationId);
        $timezone = $r->string('shop_timezone', trim: true);

        return [
            'base_url' => SelfHostedConfig::baseUrl($r),
            'api_key' => $r->string('api_key', trim: true),
            'shop_timezone' => $timezone !== null && Tz::isValid($timezone) ? $timezone : null,
            'page_size' => SelfHostedConfig::pageSize(PrestaShopPlugin::ID, 100, cap: 500),
            'max_pages' => SelfHostedConfig::maxPages(PrestaShopPlugin::ID, 200),
        ];
    }

    public static function isConfigured(?int $organizationId = null): bool {
        $config = self::resolve($organizationId);

        return $config['base_url'] !== null && $config['api_key'] !== null;
    }
}
