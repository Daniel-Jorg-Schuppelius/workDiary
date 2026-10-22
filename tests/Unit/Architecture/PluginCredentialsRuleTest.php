<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCredentialsRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Plugin-Zugangsdaten stehen nur dort, wo sie aufgelöst, eingegeben oder zu
 * einem Client verbaut werden: `…Config`, `Api/` (Clients und
 * `…ClientFactory::for($orgId)`), Admin-Controller. Dienste holen ihren Client
 * aus der Fabrik und fragen `isConfigured()` — sonst steht dieselbe Verkabelung
 * mehrfach da und eine Stelle vergisst eine Option (Umbau 2026-10-22).
 */
class PluginCredentialsRuleTest extends TestCase {
    use ScansSourceTree;

    /** Geltungsbereich: hier gehören Zugangsdaten hin. */
    private const CREDENTIAL_HOMES = ['/Config\.php$/', '#/Api/#', '/AdminController\.php$/', '#/Database/Migrations/#'];

    private const PATTERNS = [
        '/\$config\[[\'"](?:api_key|api_token|api_secret|api_password|api_client|client_secret|access_token|refresh_token|token|token_id|password|secret)[\'"]\]/' => 'Geheimnis aus der Konfiguration gelesen',
        '/\b\w+Client::fromConfig\(/' => 'Client aus Rohwerten gebaut',
        '/forgetInstance\(/' => 'Dienst-Singleton verworfen',
    ];

    /** Lexoffice zusätzlich: Dienste nehmen nur die Organisation (Scheduler-Befund 2026-10-17). */
    private const LEXOFFICE_PATTERNS = [
        '/\$\w+\[\'(?:api_key|base_url|request_interval)\'\]/' => 'Zugangsdaten aus der Konfiguration gelesen',
        '/\$(?:apiKey|baseUrl|requestInterval)\b/' => 'Zugangsdaten als Parameter oder Feld',
        '/new\s+(?:\\\\?Lexoffice\\\\API\\\\)?Client\s*\(/' => 'SDK-Client direkt gebaut',
    ];

    public function test_plugin_credentials_stay_in_config_api_and_admin(): void {
        $violations = [];

        foreach ($this->filesUnder('app/Plugins', '/\.php$/') as $path) {
            $relative = str_replace($this->repoRoot() . '/', '', $path);
            if (str_starts_with($relative, 'app/Plugins/Support/') || $this->isCredentialHome($relative)) {
                continue;
            }
            $source = (string) file_get_contents($path);
            $patterns = str_starts_with($relative, 'app/Plugins/Lexoffice/') ? self::PATTERNS + self::LEXOFFICE_PATTERNS : self::PATTERNS;
            foreach ($patterns as $pattern => $label) {
                if (preg_match($pattern, $source) === 1) {
                    $violations[] = "{$relative} ({$label})";
                }
            }
        }

        $this->assertSame([], $violations, sprintf(
            "Plugin-Zugangsdaten außerhalb von Config, Api/ und Admin-Controller:\n  %s\nClient über …ClientFactory::for(\$organizationId) holen, Bereitschaft über isConfigured() bzw. …Config::isConfigured() prüfen.",
            implode("\n  ", $violations),
        ));
    }

    private function isCredentialHome(string $relative): bool {
        foreach (self::CREDENTIAL_HOMES as $pattern) {
            if (preg_match($pattern, $relative) === 1) {
                return true;
            }
        }

        return false;
    }
}
