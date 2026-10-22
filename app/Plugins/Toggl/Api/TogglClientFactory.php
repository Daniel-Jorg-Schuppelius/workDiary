<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl\Api;

use App\Plugins\Toggl\TogglConfig;

/**
 * Die eine Stelle, die Toggl-Clients aus den Einstellungen der Organisation
 * baut; `null` heißt gebundene Organisation bzw. Einzelplatz-Konfiguration.
 * Ohne Zugangsdaten liefert sie einen Client, der {@see TogglApiClient::isConfigured()}
 * verneint — die Aufrufer melden das wie bisher selbst.
 */
class TogglClientFactory {
    public function for(?int $organizationId): TogglApiClient {
        $config = TogglConfig::resolve($organizationId);

        return new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id'], $config['request_interval']);
    }

    public function isConfigured(?int $organizationId): bool {
        return $this->for($organizationId)->isConfigured();
    }
}
