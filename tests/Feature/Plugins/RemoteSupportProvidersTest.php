<?php
/*
 * Created on   : Wed Nov 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteSupportProvidersTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Asset\AssetClass;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Time\TimeEntry;
use App\Plugins\RemoteSupport\Api\{PcvisitClient, RustDeskApiServerClient, RustDeskProClient, SplashtopClient, TeamViewerClient};
use App\Plugins\RemoteSupport\Providers\{RemoteProvider, RemoteProviders};
use App\Plugins\RemoteSupport\{RemoteSupportConfig, RemoteSupportPlugin};
use App\Plugins\RemoteSupport\Services\{RemoteDeviceRegistry, RemoteSessionImporter};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Anbieterregister des Fernwartungs-Plugins (Phase 171, MVP-1472). */
class RemoteSupportProvidersTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->organization->forceFill(['owner_id' => $owner->id])->save();
    }

    public function test_registry_lists_five_providers_with_device_types(): void {
        $this->assertSame(['anydesk', 'teamviewer', 'rustdesk', 'splashtop', 'pcvisit'], RemoteProviders::ids());
        $this->assertSame([
            'anydesk' => 'anydesk_id',
            'teamviewer' => 'teamviewer_id',
            'rustdesk' => 'rustdesk_id',
            'splashtop' => 'splashtop_id',
            'pcvisit' => 'pcvisit_id',
        ], RemoteDeviceRegistry::deviceTypes());
        $this->assertSame('RustDesk', RemoteProviders::label('rustdesk'));
        $this->assertSame('pcvisit', RemoteProviders::label('pcvisit'));
        $this->assertSame('splashtop_id', RemoteDeviceRegistry::deviceType('splashtop'));
    }

    public function test_config_resolves_each_provider_from_org_settings_over_config(): void {
        config([
            'plugins.remote-support.splashtop.enabled' => true,
            'plugins.remote-support.splashtop.api_token' => 'env-token',
            'plugins.remote-support.pcvisit.integrator_token' => 'operator-integrator',
        ]);
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => RemoteSupportPlugin::ID,
            'enabled' => true,
            'settings' => [
                'rustdesk_enabled' => true,
                'rustdesk_flavor' => 'api_server',
                'rustdesk_base_url' => ' https://rustdesk-api.example.com ',
                'rustdesk_username' => 'admin',
                'rustdesk_password' => 'secret',
                'splashtop_region' => 'com',
                'pcvisit_enabled' => true,
                'pcvisit_user_token' => 'user-token',
            ],
        ]);

        $config = RemoteSupportConfig::resolve($this->organization->id);

        $this->assertTrue($config['rustdesk']['enabled']);
        $this->assertSame('api_server', $config['rustdesk']['flavor']);
        $this->assertSame('https://rustdesk-api.example.com', $config['rustdesk']['base_url']);
        $this->assertNull($config['rustdesk']['api_token']);
        // Org-Einstellung vor Config, Config vor Vorgabe.
        $this->assertTrue($config['splashtop']['enabled']);
        $this->assertSame('env-token', $config['splashtop']['api_token']);
        $this->assertSame('com', $config['splashtop']['region']);
        $this->assertSame(SplashtopClient::DEFAULT_SERVICE_KINDS, $config['splashtop']['service_kinds']);
        // Der Integrator-Token des Betreibers gilt, solange die Organisation keinen eigenen hat.
        $this->assertSame('operator-integrator', $config['pcvisit']['integrator_token']);
        $this->assertSame('user-token', $config['pcvisit']['user_token']);
        $this->assertSame(PcvisitClient::DEFAULT_ENDPOINT, $config['pcvisit']['endpoint']);
        // Bisherige Schlüssel bleiben.
        $this->assertFalse($config['anydesk']['enabled']);
        $this->assertSame(TeamViewerClient::DEFAULT_BASE_URL, $config['teamviewer']['base_url']);
    }

    public function test_settings_schema_has_toggle_per_provider_and_private_network_switch(): void {
        $keys = array_column((new RemoteSupportPlugin)->settingsSchema(), 'key');

        foreach (RemoteProviders::ids() as $id) {
            $this->assertContains($id . '_enabled', $keys, $id);
        }
        foreach (['rustdesk_flavor', 'rustdesk_base_url', 'rustdesk_api_token', 'rustdesk_username', 'rustdesk_password', 'splashtop_region', 'splashtop_api_token', 'pcvisit_user_token', 'pcvisit_integrator_token', 'allow_private_network'] as $key) {
            $this->assertContains($key, $keys, $key);
        }
        $this->assertSame(count($keys), count(array_unique($keys)), 'Schlüssel sind eindeutig');
    }

    public function test_providers_for_builds_clients_by_flavor(): void {
        $config = RemoteSupportConfig::resolve(null);
        $config['rustdesk'] = ['enabled' => true, 'flavor' => 'pro', 'base_url' => 'https://rustdesk.example.com', 'api_token' => 't', 'username' => null, 'password' => null, 'timezone' => null];
        $config['splashtop'] = ['enabled' => true, 'region' => 'eu', 'api_token' => 't', 'team_id' => null, 'service_kinds' => 'unattended_remote'];
        $config['pcvisit'] = ['enabled' => true, 'user_token' => 'u', 'integrator_token' => 'i', 'endpoint' => PcvisitClient::DEFAULT_ENDPOINT];

        $providers = (new RemoteSessionImporter)->providersFor($config, $this->organization->id);
        $classes = array_map(static fn (RemoteProvider $p): string => $p::class, $providers);
        $this->assertSame([RustDeskProClient::class, SplashtopClient::class, PcvisitClient::class], $classes);

        $config['rustdesk']['flavor'] = 'api_server';
        $config['rustdesk']['username'] = 'admin';
        $config['rustdesk']['password'] = 'pw';
        $providers = (new RemoteSessionImporter)->providersFor($config, $this->organization->id);
        $this->assertInstanceOf(RustDeskApiServerClient::class, $providers[0]);
        $this->assertSame('rustdesk', $providers[0]->id());
        $this->assertTrue($providers[0]->isConfigured());
    }

    public function test_asset_actions_accept_new_provider_ids(): void {
        $admin = $this->orgAdmin();
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'notebook',
        ]);

        $this->actingAs($admin)
            ->post(route('assets.remote-support.id', $asset), ['provider' => 'rustdesk', 'remote_id' => '123456789'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('assets.remote-support.id', $asset), ['provider' => 'splashtop', 'remote_id' => '4a938402feabe3add440fc7cbf3eed14'])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('assets.remote-support.id', $asset), ['provider' => 'logmein', 'remote_id' => 'x'])
            ->assertSessionHasErrors('provider');

        $devices = new RemoteDeviceRegistry;
        $this->assertSame(['123456789'], $devices->remoteIds($asset, 'rustdesk'));
        $this->assertSame(['4a938402feabe3add440fc7cbf3eed14'], $devices->remoteIds($asset, 'splashtop'));

        $this->actingAs($admin)
            ->delete(route('assets.remote-support.forget', [$asset, 'rustdesk']))
            ->assertRedirect();
        $this->assertSame([], $devices->remoteIds($asset, 'rustdesk'));
    }

    public function test_panel_lists_every_provider(): void {
        config(['plugins.remote-support.enabled' => true]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'category_code' => 'workstation',
        ]);

        $html = (string) (new RemoteSupportPlugin)->renderActions('asset-show.aside', $asset);

        foreach (RemoteProviders::labels() as $label) {
            $this->assertStringContainsString($label, $html);
        }
        $this->assertStringContainsString('name="provider" value="pcvisit"', $html);
    }

    public function test_failing_provider_does_not_stop_the_others(): void {
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => RemoteSupportPlugin::ID,
            'enabled' => true,
            'settings' => [
                'teamviewer_enabled' => true,
                'teamviewer_api_key' => 'tv-token',
                'rustdesk_enabled' => true,
                'rustdesk_flavor' => 'pro',
                'rustdesk_base_url' => 'https://rustdesk.example.com',
                'rustdesk_api_token' => 'rd-token',
            ],
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $asset = Asset::factory()->create([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
            'customer_id' => $customer->id,
        ]);
        (new RemoteDeviceRegistry)->setRemoteId($asset, TeamViewerClient::ID, '123456789');

        FakePluginHttp::fake([
            'https://rustdesk.example.com/api/audits/conn*' => FakePluginHttp::response(['error' => 'forbidden'], 403),
            'https://webapi.teamviewer.com/api/v1/reports/connections*' => FakePluginHttp::response([
                'records' => [[
                    'id' => 'tv-1',
                    'deviceid' => '123456789',
                    'start_date' => CarbonImmutable::parse('2026-11-02 10:00:00')->toIso8601String(),
                    'end_date' => CarbonImmutable::parse('2026-11-02 10:30:00')->toIso8601String(),
                ]],
                'next_offset' => null,
            ]),
        ]);

        $result = (new RemoteSessionImporter)->import(
            $this->organization,
            RemoteSupportConfig::resolve($this->organization->id),
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-03'),
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['errors']);
        $this->assertStringContainsString('RustDesk', $result['error']);
        $this->assertStringContainsString('HTTP 403', $result['error']);
        $this->assertSame(1, TimeEntry::query()->count());
        $this->assertDatabaseHas('plugin_errors', ['plugin_id' => RemoteSupportPlugin::ID, 'phase' => 'sync:rustdesk']);
    }
}
