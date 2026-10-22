<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Clockify\Api;

use App\Plugins\Clockify\ClockifyConfig;

/**
 * Die eine Stelle, die Clockify-Clients aus den Einstellungen der Organisation
 * baut; `null` heißt gebundene Organisation bzw. Einzelplatz-Konfiguration.
 * Ohne Zugangsdaten liefert sie einen Client, der {@see ClockifyApiClient::isConfigured()}
 * verneint — die Aufrufer melden das wie bisher selbst.
 */
class ClockifyClientFactory {
    public function for(?int $organizationId): ClockifyApiClient {
        $config = ClockifyConfig::resolve($organizationId);

        return new ClockifyApiClient($config['api_key'], $config['base_url'], $config['reports_base_url'], $config['workspace_id']);
    }

    public function isConfigured(?int $organizationId): bool {
        return $this->for($organizationId)->isConfigured();
    }
}
