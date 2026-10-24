<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeantimeClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Leantime\Api;

use App\Plugins\Leantime\LeantimeConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Leantime-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class LeantimeClientFactory {
    public function isConfigured(int $organizationId): bool {
        return LeantimeConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): LeantimeClient {
        $config = LeantimeConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_key'] === null) {
            throw new RuntimeException('Leantime ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new LeantimeClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_key']);
    }
}
