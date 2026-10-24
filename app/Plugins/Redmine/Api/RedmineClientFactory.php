<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine\Api;

use App\Plugins\Redmine\RedmineConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Redmine-Client aus den Plugin-Einstellungen einer
 * Organisation; die SSRF-Prüfung der Basis-URL macht die {@see PluginHttpFactory}.
 */
class RedmineClientFactory {
    public function isConfigured(int $organizationId): bool {
        return RedmineConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): RedmineClient {
        $config = RedmineConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_key'] === null) {
            throw new RuntimeException('Redmine ist für diese Organisation nicht eingerichtet (Basis-URL oder API-Schlüssel fehlt).');
        }

        return new RedmineClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_key'], $config['page_size'], $config['max_pages']);
    }
}
