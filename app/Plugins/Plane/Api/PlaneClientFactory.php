<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaneClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Plane\Api;

use App\Plugins\Plane\PlaneConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den Plane-Client aus den Plugin-Einstellungen einer Organisation;
 * die SSRF-Prüfung der Adresse macht die {@see PluginHttpFactory}.
 */
class PlaneClientFactory {
    public function isConfigured(int $organizationId): bool {
        return PlaneConfig::isConfigured($organizationId);
    }

    public function for(int $organizationId): PlaneClient {
        $config = PlaneConfig::resolve($organizationId);
        if ($config['base_url'] === null || $config['workspace_slug'] === null || $config['api_key'] === null) {
            throw new RuntimeException('Plane ist für diese Organisation nicht eingerichtet (Adresse oder Zugang fehlen).');
        }

        return new PlaneClient(app(PluginHttpFactory::class), $config['base_url'], $config['workspace_slug'], $config['api_key'], $config['page_size'], $config['max_pages']);
    }
}
