<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Api;

use App\Plugins\Billbee\BillbeeConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den typisierten Billbee-Client je Organisation aus der aufgelösten
 * Konfiguration (plugin_settings → ENV-Fallback). Die {@see PluginHttpFactory}
 * wird ERST zur Aufrufzeit aus dem Container gelöst — Tests binden den
 * Fake-Transport (FakePluginHttp).
 */
class BillbeeClientFactory {
    public function for(int $organizationId): BillbeeApiClient {
        $config = BillbeeConfig::resolve($organizationId);
        if (empty($config['api_key']) || empty($config['username']) || empty($config['api_password'])) {
            throw new RuntimeException((string) __('Billbee ist für diese Organisation nicht konfiguriert (API-Key/Benutzer/API-Passwort fehlen).'));
        }

        return new BillbeeApiClient(
            app(PluginHttpFactory::class),
            (string) $config['api_key'],
            (string) $config['username'],
            (string) $config['api_password'],
            (string) $config['base_url'],
            (float) config('plugins.billbee.request_interval', 0.5),
        );
    }
}
