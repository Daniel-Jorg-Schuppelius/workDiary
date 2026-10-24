<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PaperlessClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Paperless\Api;

use App\Plugins\Paperless\PaperlessConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Paperless-ngx-Client aus den Plugin-Einstellungen einer
 * Organisation; die SSRF-Prüfung der Basis-URL macht die {@see PluginHttpFactory}.
 */
class PaperlessClientFactory {
    public function isConfigured(int $organizationId): bool {
        return PaperlessConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): PaperlessClient {
        $config = PaperlessConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['api_token'] === null) {
            throw new RuntimeException('Paperless-ngx ist für diese Organisation nicht eingerichtet (Basis-URL oder Token fehlt).');
        }

        return new PaperlessClient(app(PluginHttpFactory::class), $config['base_url'], $config['api_token'], $config['max_id_pages']);
    }

    /** Basis-URL der Organisation (Instanzidentität), ohne Token. */
    public function baseUrl(int $organizationId): ?string {
        return PaperlessConfig::resolve($organizationId)['base_url'];
    }
}
