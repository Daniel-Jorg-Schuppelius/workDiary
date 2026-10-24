<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Api;

use App\Plugins\Support\PluginHttpFactory;
use App\Plugins\WooCommerce\WooCommerceConfig;
use RuntimeException;

/**
 * Baut den WooCommerce-Client aus den Plugin-Einstellungen einer
 * Organisation; die SSRF-Prüfung der Shop-Adresse macht die {@see PluginHttpFactory}.
 */
class WooCommerceClientFactory {
    public function isConfigured(int $organizationId): bool {
        return WooCommerceConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): WooCommerceClient {
        $config = WooCommerceConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['consumer_key'] === null || $config['consumer_secret'] === null) {
            throw new RuntimeException('WooCommerce ist für diese Organisation nicht eingerichtet (Shop-Adresse oder Schlüssel fehlen).');
        }

        return new WooCommerceClient(app(PluginHttpFactory::class), $config['base_url'], $config['consumer_key'], $config['consumer_secret'], $config['page_size'], $config['max_pages']);
    }

    public function baseUrl(int $organizationId): ?string {
        return WooCommerceConfig::resolve($organizationId)['base_url'];
    }
}
