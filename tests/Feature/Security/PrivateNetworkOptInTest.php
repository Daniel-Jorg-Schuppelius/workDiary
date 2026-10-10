<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivateNetworkOptInTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\Audit\AuditLog;
use App\Models\Platform\PluginSetting;
use App\Plugins\CalDav\Models\CalDavConnection;
use App\Plugins\CalDav\Services\{GuzzleCalDavGatewayFactory, HttpCalDavGateway};
use App\Plugins\Kimai\{KimaiConfig, KimaiPlugin};
use App\Plugins\Kimai\Services\KimaiImportService;
use App\Plugins\OpenProject\Api\OpenProjectApiClient;
use App\Plugins\OpenProject\{OpenProjectConfig, OpenProjectPlugin};
use App\Plugins\Webdav\Models\WebdavConnection;
use App\Plugins\Webdav\Services\{GuzzleWebdavGatewayFactory, HttpWebdavGateway};
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\ZammadClientGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Phase 137 (E22): Freigabe privater Adressen je Anbindung wie bei CardDAV —
 * WebDAV, CalDAV und Zammad am Verbindungsdatensatz, selbst gehostetes Kimai
 * und OpenProject als Plugin-Einstellung. Ohne Freigabe bleibt die Schranke
 * hart, mit Freigabe wirkt sie nur, solange der Betreiber das Opt-in zulässt.
 */
final class PrivateNetworkOptInTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const PRIVATE_URL = 'http://10.0.0.5/remote.php/dav';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app()->setLocale('de');
    }

    private function webdav(bool $optIn): WebdavConnection {
        return new WebdavConnection([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => self::PRIVATE_URL,
            'username' => 'bot',
            'app_password' => 'secret',
            'default_folder' => 'WorkDiary',
            'allow_private_network' => $optIn,
            'active' => true,
        ]);
    }

    private function caldav(bool $optIn): CalDavConnection {
        return new CalDavConnection([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => self::PRIVATE_URL,
            'username' => 'bot',
            'app_password' => 'secret',
            'calendar_path' => 'calendars/bot/team',
            'allow_private_network' => $optIn,
            'active' => true,
        ]);
    }

    private function zammad(bool $optIn): ZammadConnection {
        return new ZammadConnection([
            'organization_id' => $this->organization->id,
            'name' => 'Support',
            'base_url' => 'http://10.0.0.7',
            'api_token' => 'token',
            'allow_private_network' => $optIn,
            'active' => true,
        ]);
    }

    public function test_webdav_without_opt_in_names_the_switch_of_the_storage(): void {
        try {
            (new GuzzleWebdavGatewayFactory)->for($this->webdav(false));
            $this->fail('Interne Adresse wurde nicht abgewiesen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(__('webdav::webdav.flash.private_hint'), $e->getMessage());
        }
    }

    public function test_webdav_with_opt_in_reaches_the_private_server(): void {
        $this->assertInstanceOf(HttpWebdavGateway::class, (new GuzzleWebdavGatewayFactory)->for($this->webdav(true)));
    }

    public function test_caldav_without_opt_in_names_the_switch_of_the_connection(): void {
        try {
            (new GuzzleCalDavGatewayFactory)->for($this->caldav(false));
            $this->fail('Interne Adresse wurde nicht abgewiesen.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(__('caldav::caldav.flash.private_hint'), $e->getMessage());
        }
    }

    public function test_caldav_with_opt_in_reaches_the_private_server(): void {
        $this->assertInstanceOf(HttpCalDavGateway::class, (new GuzzleCalDavGatewayFactory)->for($this->caldav(true)));
    }

    public function test_zammad_honours_the_opt_in(): void {
        $this->assertInstanceOf(ZammadClientGateway::class, ZammadClientGateway::forConnection($this->zammad(true)));

        $this->expectException(RuntimeException::class);
        ZammadClientGateway::forConnection($this->zammad(false));
    }

    /** Der Betreiber-Schalter sticht die Freigabe der Anbindung (SaaS-Betrieb). */
    public function test_operator_switch_overrides_the_connection_opt_in(): void {
        config(['plugins.allow_private_network_opt_in' => false]);

        foreach ([
            fn () => (new GuzzleWebdavGatewayFactory)->for($this->webdav(true)),
            fn () => (new GuzzleCalDavGatewayFactory)->for($this->caldav(true)),
            fn () => ZammadClientGateway::forConnection($this->zammad(true)),
        ] as $build) {
            try {
                $build();
                $this->fail('Freigabe wirkte trotz Betreiber-Sperre.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** Speichern prüft schon die Adresse; mit Freigabe wird gespeichert und die Freigabe protokolliert. */
    public function test_webdav_and_caldav_admin_store_the_audited_opt_in(): void {
        $admin = $this->orgAdmin();
        $forms = [
            'admin.webdav.connection.store' => ['default_folder' => 'WorkDiary', 'event' => 'webdav.connection_saved', 'ns' => 'webdav'],
            'admin.caldav.connection.store' => ['calendar_path' => 'calendars/bot/team', 'event' => 'caldav.connection_saved', 'ns' => 'caldav'],
        ];

        foreach ($forms as $route => $extra) {
            $payload = ['name' => 'Nextcloud', 'base_url' => self::PRIVATE_URL, 'username' => 'bot', 'app_password' => 'secret', 'active' => 1]
                + array_diff_key($extra, ['event' => true, 'ns' => true]);

            $this->actingAs($admin)->post(route($route), $payload)
                ->assertSessionHas('error', __($extra['ns'] . '::' . $extra['ns'] . '.flash.private_url_blocked'));

            $this->actingAs($admin)->post(route($route), $payload + ['allow_private_network' => 1])
                ->assertSessionHas('success');

            $audit = AuditLog::query()->withoutGlobalScopes()->where('event', $extra['event'])->latest('id')->firstOrFail();
            $this->assertTrue($audit->changes['allow_private_network'] ?? null, $route);
        }

        $this->assertTrue(WebdavConnection::query()->firstOrFail()->allow_private_network);
        $this->assertTrue(CalDavConnection::query()->firstOrFail()->allow_private_network);
    }

    /** Selbst gehostetes Kimai: Plugin-Einstellung „Private Adressen erlauben“ (GitLab-Muster). */
    public function test_kimai_setting_opens_the_private_instance(): void {
        $setting = PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => KimaiPlugin::ID,
            'enabled' => true,
            'settings' => ['base_url' => 'http://10.0.0.6', 'api_token' => 'secret-token', 'single_user_mode' => true],
        ]);
        $fake = FakePluginHttp::fake(['http://10.0.0.6/api/timesheets*' => FakePluginHttp::response([])]);

        $blocked = (new KimaiImportService)->importFromApi($this->organization, KimaiConfig::resolve($this->organization->id));
        $this->assertStringContainsString('„' . __('Private Adressen erlauben') . '“', (string) ($blocked['error'] ?? ''));

        $setting->forceFill(['settings' => $setting->settings + ['allow_private_network' => true]])->save();
        $result = (new KimaiImportService)->importFromApi($this->organization, KimaiConfig::resolve($this->organization->id));

        $this->assertArrayNotHasKey('error', $result);
        $fake->assertSent(fn ($request): bool => str_starts_with((string) $request->getUri(), 'http://10.0.0.6/api/timesheets'));
    }

    /** Selbst gehostetes OpenProject: dieselbe Plugin-Einstellung, auch ohne Org-Kontext (Outbox, Scheduler). */
    public function test_openproject_setting_opens_the_private_instance(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OpenProjectPlugin::ID,
            'enabled' => true,
            'settings' => ['base_url' => 'http://10.0.0.8', 'api_token' => 'secret-token', 'allow_private_network' => true],
        ]);
        FakePluginHttp::fake(['http://10.0.0.8/api/v3/users/me' => FakePluginHttp::response(['id' => 1])]);
        app()->forgetInstance('currentOrganization');

        $config = OpenProjectConfig::resolve($this->organization->id);
        $this->assertTrue($config['allow_private_network']);
        $this->assertTrue((new OpenProjectApiClient($config['api_token'], $config['base_url'], $config['allow_private_network']))->ping());

        $this->expectException(RuntimeException::class);
        (new OpenProjectApiClient($config['api_token'], $config['base_url']))->ping();
    }
}
