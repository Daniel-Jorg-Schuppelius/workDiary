<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler\Api;

use App\Plugins\BuchhaltungsButler\BhbConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den typisierten BuchhaltungsButler-Client je Organisation aus der
 * aufgelösten Konfiguration (plugin_settings → ENV-Fallback). Die
 * {@see PluginHttpFactory} wird ERST zur Aufrufzeit aus dem Container
 * gelöst — Tests binden den Fake-Transport (FakePluginHttp).
 */
class BhbClientFactory {
    public function for(int $organizationId): BhbApiClient {
        $config = BhbConfig::resolve($organizationId);
        if (empty($config['api_client']) || empty($config['api_secret']) || empty($config['api_key'])) {
            throw new RuntimeException((string) __('BuchhaltungsButler ist für diese Organisation nicht konfiguriert (API-Client/-Secret/-Key fehlen).'));
        }

        $ratePerMinute = max(1, (int) config('plugins.buchhaltungsbutler.rate_limit_per_minute', 100));

        return new BhbApiClient(
            app(PluginHttpFactory::class),
            (string) $config['api_client'],
            (string) $config['api_secret'],
            (string) $config['api_key'],
            (string) $config['base_url'],
            60.0 / $ratePerMinute,
        );
    }
}
