<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Api;

use App\Plugins\SevDesk\SevDeskConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den typisierten sevDesk-Client je Organisation aus der aufgelösten
 * Konfiguration (plugin_settings → ENV-Fallback). Die {@see PluginHttpFactory}
 * wird bewusst ERST zur Aufrufzeit aus dem Container gelöst — Tests binden
 * den Fake-Transport (FakePluginHttp) nach dem Plugin-Boot.
 */
class SevDeskClientFactory {
    public function for(int $organizationId): SevDeskClient {
        $config = SevDeskConfig::resolve($organizationId);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.sevdesk_not_configured'));
        }

        return new SevDeskClient(
            app(PluginHttpFactory::class),
            (string) $config['api_key'],
            (string) $config['base_url'],
            $organizationId,
        );
    }
}
