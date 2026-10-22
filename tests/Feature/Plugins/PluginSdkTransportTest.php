<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginSdkTransportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Plugins\Support\{PluginApiException, PluginHttpFactory};
use Closure;
use GuzzleHttp\Client as GuzzleClient;
use Tests\TestCase;

/** SDK-Clients bekommen dieselben Schranken wie PluginApiClient: Adressbindung (sf-3) und geprüfte Weiterleitungen (ssrf-1). */
final class PluginSdkTransportTest extends TestCase {
    private function factory(): object {
        return new class extends PluginHttpFactory {
            public function transport(string $baseUrl, bool $allowPrivateNetwork = false): ?GuzzleClient {
                return $this->sdkTransport($baseUrl, $allowPrivateNetwork);
            }
        };
    }

    public function test_a_host_that_resolves_inwards_is_refused(): void {
        $this->expectException(PluginApiException::class);
        $this->factory()->transport('https://localhost/v1');
    }

    public function test_redirects_are_guarded_and_the_private_opt_in_skips_the_binding(): void {
        $private = $this->factory()->transport('https://localhost/v1', true);
        $this->assertInstanceOf(Closure::class, $private?->getConfig('allow_redirects')['on_redirect'] ?? null);
        $this->assertNull($private?->getConfig('curl'));

        $public = $this->factory()->transport('https://93.184.216.34/v1');
        $this->assertInstanceOf(Closure::class, $public?->getConfig('allow_redirects')['on_redirect'] ?? null);
        $this->assertSame('https://93.184.216.34/v1', (string) $public?->getConfig('base_uri'));
    }
}
