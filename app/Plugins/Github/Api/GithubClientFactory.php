<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GithubClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Github\Api;

use App\Plugins\Github\GithubConfig;
use App\Plugins\Support\PluginHttpFactory;
use RuntimeException;

/**
 * Baut den typisierten GitHub-Client je Organisation aus der aufgelösten
 * Konfiguration (plugin_settings → ENV-Fallback). Die {@see PluginHttpFactory}
 * wird bewusst ERST zur Aufrufzeit aus dem Container gelöst — Tests binden
 * den Fake-Transport (FakePluginHttp) nach dem Plugin-Boot.
 */
class GithubClientFactory {
    public function for(int $organizationId): GithubClient {
        $config = GithubConfig::resolve($organizationId);
        if (empty($config['api_token'])) {
            throw new RuntimeException('GitHub ist nicht konfiguriert (API-Token fehlt).');
        }

        return new GithubClient(
            app(PluginHttpFactory::class),
            (string) $config['api_token'],
            (string) $config['base_url'],
        );
    }
}
