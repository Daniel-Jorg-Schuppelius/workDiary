<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware\Api;

use App\Plugins\Shopware\ShopwareConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Shopware-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung von Shop- und Token-Adresse macht die {@see PluginHttpFactory}.
 */
class ShopwareClientFactory {
    public function isConfigured(int $organizationId): bool {
        return ShopwareConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): ShopwareClient {
        $config = ShopwareConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['client_id'] === null || $config['client_secret'] === null) {
            throw new RuntimeException('Shopware ist für diese Organisation nicht eingerichtet (Shop-Adresse oder Integration fehlen).');
        }

        return new ShopwareClient(app(PluginHttpFactory::class), $config['base_url'], $config['client_id'], $config['client_secret'], $config['page_size'], $config['max_pages']);
    }

    public function baseUrl(int $organizationId): ?string {
        return ShopwareConfig::resolve($organizationId)['base_url'];
    }
}
