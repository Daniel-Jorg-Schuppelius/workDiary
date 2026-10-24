<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreeScoutClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\FreeScout\Api;

use App\Plugins\FreeScout\FreeScoutConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den FreeScout-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class FreeScoutClientFactory {
    public function isConfigured(int $organizationId): bool {
        return FreeScoutConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): FreeScoutClient {
        $config = FreeScoutConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_key'] === null) {
            throw new RuntimeException('FreeScout ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new FreeScoutClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_key'], $config['page_size'], $config['max_pages']);
    }
}
