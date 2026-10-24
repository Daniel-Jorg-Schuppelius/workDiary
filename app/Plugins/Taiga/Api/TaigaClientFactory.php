<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaigaClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Taiga\Api;

use App\Plugins\Support\PluginHttpFactory;
use App\Plugins\Taiga\TaigaConfig;
use RuntimeException;

/**
 * Baut den Taiga-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class TaigaClientFactory {
    public function isConfigured(int $organizationId): bool {
        return TaigaConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): TaigaClient {
        $config = TaigaConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['username'] === null || $config['password'] === null) {
            throw new RuntimeException('Taiga ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new TaigaClient(app(PluginHttpFactory::class), $config['base_url'], $config['username'], $config['password'], $config['page_size'], $config['max_pages']);
    }
}
