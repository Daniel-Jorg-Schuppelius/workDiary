<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Kimai\Api;

use App\Models\Platform\Organization;
use App\Plugins\Kimai\KimaiConfig;
use App\Plugins\Support\PluginOrgContext;
use App\Support\Tz;

/**
 * Die eine Stelle, die Kimai-Clients aus den Einstellungen der Organisation
 * baut; `null` heißt gebundene Organisation bzw. Einzelplatz-Konfiguration.
 * Ohne Zugangsdaten liefert sie einen Client, der {@see KimaiApiClient::isConfigured()}
 * verneint — die Aufrufer melden das wie bisher selbst.
 */
class KimaiClientFactory {
    public function for(?int $organizationId): KimaiApiClient {
        $organizationId ??= PluginOrgContext::currentId();
        $config = KimaiConfig::resolve($organizationId);
        $organization = $organizationId !== null ? Organization::query()->withoutGlobalScopes()->find($organizationId) : null;

        return new KimaiApiClient(
            $config['api_token'],
            $config['base_url'],
            $organization instanceof Organization ? Tz::ofOrganization($organization) : null,
            $config['allow_private_network'],
        );
    }

    public function isConfigured(?int $organizationId): bool {
        return $this->for($organizationId)->isConfigured();
    }
}
