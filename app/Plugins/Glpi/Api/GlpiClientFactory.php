<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlpiClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Glpi\Api;

use App\Plugins\Glpi\GlpiConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den GLPI-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class GlpiClientFactory {
    public function isConfigured(int $organizationId): bool {
        return GlpiConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): GlpiClient {
        $config = GlpiConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['user_token'] === null) {
            throw new RuntimeException('GLPI ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new GlpiClient(app(PluginHttpFactory::class), $config['base_url'], $config['app_token'], $config['user_token'], $config['page_size'], $config['max_pages']);
    }
}
