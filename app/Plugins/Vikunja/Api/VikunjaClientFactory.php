<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VikunjaClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Vikunja\Api;

use App\Plugins\Support\PluginHttpFactory;
use App\Plugins\Vikunja\VikunjaConfig;
use RuntimeException;

/**
 * Baut den Vikunja-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class VikunjaClientFactory {
    public function isConfigured(int $organizationId): bool {
        return VikunjaConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): VikunjaClient {
        $config = VikunjaConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_token'] === null) {
            throw new RuntimeException('Vikunja ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new VikunjaClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_token'], $config['page_size'], $config['max_pages']);
    }
}
