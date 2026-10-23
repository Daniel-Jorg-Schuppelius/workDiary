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

use App\Plugins\Toggl\Enums\TogglSource;
use App\Plugins\Toggl\TogglConfig;

/**
 * Die eine Stelle, die Toggl-Clients aus den Einstellungen der Organisation
 * baut; `null` heißt gebundene Organisation bzw. Einzelplatz-Konfiguration.
 * Gebaut wird nur der Client der gewählten Quelle (Feature 178) — der andere
 * Zugang bleibt unberührt, auch wenn er noch hinterlegt ist.
 * Ohne Zugangsdaten liefert sie einen Client, der {@see TogglApi::isConfigured()}
 * verneint — die Aufrufer melden das wie bisher selbst.
 */
class TogglClientFactory {
    public function for(?int $organizationId): TogglApi {
        $config = TogglConfig::resolve($organizationId);

        return match ($config['source']) {
            TogglSource::Track => new TogglApiClient($config['api_token'], $config['base_url'], $config['workspace_id'], $config['request_interval']),
            TogglSource::Focus => new TogglFocusApiClient(
                $config['focus_api_key'],
                $config['focus_base_url'],
                $config['accounts_url'],
                $config['focus_organization_id'],
                $config['focus_workspace_id'],
                $config['request_interval'],
            ),
        };
    }

    public function isConfigured(?int $organizationId): bool {
        return $this->for($organizationId)->isConfigured();
    }
}
