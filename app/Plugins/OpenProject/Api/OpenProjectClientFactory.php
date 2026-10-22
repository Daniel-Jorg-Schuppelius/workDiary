<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenProjectClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OpenProject\Api;

use App\Plugins\OpenProject\OpenProjectConfig;

/**
 * Die eine Stelle, die OpenProject-Clients aus den Einstellungen der Organisation
 * baut; `null` heißt gebundene Organisation bzw. Einzelplatz-Konfiguration.
 * Ohne Zugangsdaten liefert sie einen Client, der {@see OpenProjectApiClient::isConfigured()}
 * verneint — die Aufrufer melden das wie bisher selbst.
 */
class OpenProjectClientFactory {
    public function for(?int $organizationId): OpenProjectApiClient {
        $config = OpenProjectConfig::resolve($organizationId);

        return new OpenProjectApiClient($config['api_token'], $config['base_url'], $config['allow_private_network']);
    }

    public function isConfigured(?int $organizationId): bool {
        return $this->for($organizationId)->isConfigured();
    }
}
