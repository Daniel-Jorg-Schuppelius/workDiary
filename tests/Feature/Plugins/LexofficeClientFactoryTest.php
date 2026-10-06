<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeClientFactoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Jobs\SyncOwnerVouchersJob;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k2-02: Lexoffice baute den Client an 17
 * Stellen selbst. Anfrageabstand der Organisation, Wiederholungsbudget und
 * API-Sperre griffen jeweils nur an einem Teil.
 */
final class LexofficeClientFactoryTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key', 'request_interval' => '1.25']);
    }

    private function linkedCustomer(): Customer {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'contact-1',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
        ]);

        return $customer;
    }

    /** Fake, der die angefragten Abstände festhält. */
    private function recordingHttp(): FakePluginHttp {
        $fake = new class extends FakePluginHttp {
            /** @var list<float> */
            public array $intervals = [];

            public function client(string $pluginId, string $baseUrl, float $requestInterval = 0.0, ?bool $allowPrivateNetwork = null): PluginApiClient {
                $this->intervals[] = $requestInterval;

                return parent::client($pluginId, $baseUrl, $requestInterval, $allowPrivateNetwork);
            }
        };
        app()->instance(PluginHttpFactory::class, $fake);

        return $fake;
    }

    public function test_every_client_gets_the_same_retry_budget(): void {
        FakePluginHttp::fake();

        $this->assertSame(LexofficeClientFactory::MAX_RETRIES, app(LexofficeClientFactory::class)->make('lex-key', 'https://api.lexoffice.io/v1')->getMaxRetries());
        $this->assertSame(LexofficeClientFactory::MAX_RETRIES, app(LexofficeClientFactory::class)->fromConfig(LexofficeConfig::resolve($this->organization->id))->getMaxRetries());
    }

    public function test_a_queued_run_uses_the_interval_of_its_organization_without_bound_context(): void {
        $customer = $this->linkedCustomer();
        $fake = $this->recordingHttp();
        // Die Queue vergisst den Organisationskontext vor jedem Job.
        app()->forgetInstance('currentOrganization');

        (new SyncOwnerVouchersJob($this->organization->id, 'customer', $customer->id))->withFakeQueueInteractions()->handle();

        $this->assertNotSame([], $fake->intervals);
        $this->assertSame([1.25], array_values(array_unique($fake->intervals)));
    }

    public function test_a_job_goes_back_to_the_queue_while_another_run_holds_the_api_lock(): void {
        $customer = $this->linkedCustomer();
        $fake = FakePluginHttp::fake();
        Sleep::fake(syncWithCarbon: true);
        $this->assertTrue(Cache::lock(LexofficeConfig::apiLockKey($this->organization->id), 600)->get());

        $job = (new SyncOwnerVouchersJob($this->organization->id, 'customer', $customer->id))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(120);
        $fake->assertNothingSent();
    }

    public function test_the_sync_button_reports_a_running_sync_instead_of_calling_the_api(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = $this->linkedCustomer();
        $fake = FakePluginHttp::fake();
        Sleep::fake(syncWithCarbon: true);
        $this->assertTrue(Cache::lock(LexofficeConfig::apiLockKey($this->organization->id), 600)->get());

        $this->actingAs($admin)
            ->post(route('customers.lexoffice.sync-vouchers', $customer))
            ->assertRedirect()
            ->assertSessionHas('error', __('Lexoffice wird gerade von einem anderen Lauf abgeglichen. Bitte versuchen Sie es in einigen Minuten erneut.'));

        $fake->assertNothingSent();
    }
}
