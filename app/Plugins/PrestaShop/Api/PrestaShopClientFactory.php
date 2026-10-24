<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Api;

use App\Plugins\PrestaShop\PrestaShopConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den PrestaShop-Client aus den Plugin-Einstellungen einer
 * Organisation; die SSRF-Prüfung der Shop-Adresse macht die {@see PluginHttpFactory}.
 */
class PrestaShopClientFactory {
    public function isConfigured(int $organizationId): bool {
        return PrestaShopConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): PrestaShopClient {
        $config = PrestaShopConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_key'] === null) {
            throw new RuntimeException('PrestaShop ist für diese Organisation nicht eingerichtet (Shop-Adresse oder Schlüssel fehlen).');
        }

        return new PrestaShopClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_key'], $config['page_size'], $config['max_pages']);
    }

    /** Zeitzone, in der der Shop seine Zeitstempel ohne Versatz liefert; null = die der Organisation. */
    public function shopTimezone(int $organizationId): ?string {
        return PrestaShopConfig::resolve($organizationId)['shop_timezone'];
    }
}
