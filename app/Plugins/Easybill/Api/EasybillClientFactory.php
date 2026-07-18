<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Api;

use App\Plugins\Easybill\EasybillConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den typisierten easybill-Client je Organisation aus der aufgelösten
 * Konfiguration (plugin_settings → ENV-Fallback). Die {@see PluginHttpFactory}
 * wird ERST zur Aufrufzeit aus dem Container gelöst — Tests binden den
 * Fake-Transport (FakePluginHttp) nach dem Plugin-Boot.
 */
class EasybillClientFactory {
    public function for(int $organizationId): EasybillClient {
        $config = EasybillConfig::resolve($organizationId);
        if (empty($config['api_key'])) {
            throw new RuntimeException((string) __('finance.error.easybill_not_configured'));
        }

        return new EasybillClient(
            app(PluginHttpFactory::class),
            (string) $config['api_key'],
            (string) $config['base_url'],
            (int) $config['rate_limit_per_minute'],
        );
    }
}
