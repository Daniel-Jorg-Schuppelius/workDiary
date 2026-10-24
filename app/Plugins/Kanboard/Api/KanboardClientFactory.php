<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanboardClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kanboard\Api;

use App\Plugins\Kanboard\KanboardConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Kanboard-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class KanboardClientFactory {
    public function isConfigured(int $organizationId): bool {
        return KanboardConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): KanboardClient {
        $config = KanboardConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_token'] === null) {
            throw new RuntimeException('Kanboard ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new KanboardClient(app(PluginHttpFactory::class), $config['base_url'], $config['username'], $config['api_token']);
    }
}
