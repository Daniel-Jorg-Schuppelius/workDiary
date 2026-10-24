<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea\Api;

use App\Plugins\Gitea\GiteaConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Gitea-/Forgejo-Client aus den Plugin-Einstellungen einer
 * Organisation; die SSRF-Prüfung der Basis-URL macht die {@see PluginHttpFactory}.
 */
class GiteaClientFactory {
    public function isConfigured(int $organizationId): bool {
        return GiteaConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): GiteaClient {
        $config = GiteaConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_token'] === null) {
            throw new RuntimeException('Gitea/Forgejo ist für diese Organisation nicht eingerichtet (Basis-URL oder Token fehlt).');
        }

        return new GiteaClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_token'], $config['page_size'], $config['max_pages']);
    }
}
