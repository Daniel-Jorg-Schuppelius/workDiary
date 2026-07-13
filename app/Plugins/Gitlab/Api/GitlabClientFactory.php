<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabClientFactory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Api;

use App\Plugins\Gitlab\GitlabConfig;
use App\Plugins\Support\PluginHttpFactory;
use App\Support\UrlSafety;
use RuntimeException;

/**
 * Baut den typisierten GitLab-Client je Organisation aus der aufgelösten
 * Konfiguration (plugin_settings → ENV-Fallback). Die org-konfigurierbare
 * Instanz-URL (self-hosted) läuft durch die SSRF-Leitplanke: ohne die
 * ausdrückliche Freigabe privater Adressen (`allow_private_network`, Muster
 * JTL-Wawi) muss das Ziel öffentlich routbar sein. Die {@see PluginHttpFactory}
 * wird bewusst ERST zur Aufrufzeit aus dem Container gelöst — Tests binden
 * den Fake-Transport (FakePluginHttp) nach dem Plugin-Boot.
 */
class GitlabClientFactory {
    public function for(int $organizationId): GitlabClient {
        $config = GitlabConfig::resolve($organizationId);
        if (empty($config['api_token'])) {
            throw new RuntimeException('GitLab ist nicht konfiguriert (API-Token fehlt).');
        }

        $baseUrl = (string) $config['base_url'];
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true) || (string) parse_url($baseUrl, PHP_URL_HOST) === '') {
            throw new RuntimeException('GitLab: Die Instanz-URL ist keine gültige http(s)-Adresse.');
        }
        if (! $config['allow_private_network'] && ! UrlSafety::isPubliclyRoutableHttpUrl($baseUrl)) {
            throw new RuntimeException(
                'GitLab: Die Instanz-URL zeigt auf eine private/interne Adresse. '
                . 'Für eine On-Premise-Instanz im eigenen Netz muss die Freigabe privater Adressen ausdrücklich aktiviert werden.'
            );
        }

        return new GitlabClient(
            app(PluginHttpFactory::class),
            (string) $config['api_token'],
            $baseUrl,
        );
    }
}
