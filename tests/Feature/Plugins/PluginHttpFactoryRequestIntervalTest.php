<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginHttpFactoryRequestIntervalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Plugins\Support\PluginHttpFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\InteractsWithPlugins;
use Tests\TestCase;

/**
 * Generischer Anfrageabstand für alle Plugin-Clients: Einstellung
 * `request_interval` je Organisation, sonst bekannte Vorgabe, sonst 0.
 * Ein explizit übergebenes Intervall gewinnt immer.
 */
class PluginHttpFactoryRequestIntervalTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_known_default_applies_without_setting(): void {
        $client = (new PluginHttpFactory)->client('lexoffice', 'https://api.lexoffice.io/v1');

        $this->assertSame(0.5, $client->getRequestInterval());
    }

    public function test_unknown_plugin_stays_unthrottled(): void {
        $client = (new PluginHttpFactory)->client('zammad', 'https://zammad.example.test/api/v1');

        $this->assertSame(0.0, $client->getRequestInterval());
        $this->assertSame(0.0, PluginHttpFactory::configuredRequestInterval('zammad'));
    }

    public function test_organization_setting_overrides_for_any_plugin_and_is_clamped(): void {
        $this->enablePluginFor($this->organization, 'zammad', ['request_interval' => '1.5']);
        $this->assertSame(1.5, (new PluginHttpFactory)->client('zammad', 'https://zammad.example.test/api/v1')->getRequestInterval());

        $this->enablePluginFor($this->organization, 'sevdesk', ['request_interval' => '99']);
        $this->assertSame(PluginHttpFactory::MAX_REQUEST_INTERVAL, (new PluginHttpFactory)->client('sevdesk', 'https://my.sevdesk.de/api/v1')->getRequestInterval());
    }

    public function test_explicit_interval_from_the_caller_wins(): void {
        $this->enablePluginFor($this->organization, 'lexoffice', ['request_interval' => '2']);

        $this->assertSame(0.25, (new PluginHttpFactory)->client('lexoffice', 'https://api.lexoffice.io/v1', 0.25)->getRequestInterval());
    }
}
