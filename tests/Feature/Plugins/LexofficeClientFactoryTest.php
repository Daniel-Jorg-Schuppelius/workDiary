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

use APIToolkit\Contracts\Abstracts\API\ClientAbstract;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\Exceptions\LexofficeNotConfiguredException;
use App\Plugins\Lexoffice\Jobs\SyncOwnerVouchersJob;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficePlugin};
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Lexoffice\API\Client;
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

            /** @var list<?float> */
            public array $sdkIntervals = [];

            public function client(string $pluginId, string $baseUrl, float $requestInterval = 0.0, ?bool $allowPrivateNetwork = null): PluginApiClient {
                $this->intervals[] = $requestInterval;

                return parent::client($pluginId, $baseUrl, $requestInterval, $allowPrivateNetwork);
            }

            public function sdkClient(string $pluginId, string $baseUrl, callable $make, ?float $requestInterval = null): ClientAbstract {
                $this->sdkIntervals[] = $requestInterval;

                return parent::sdkClient($pluginId, $baseUrl, $make, $requestInterval);
            }
        };
        app()->instance(PluginHttpFactory::class, $fake);

        return $fake;
    }

    public function test_a_queued_run_uses_the_interval_of_its_organization_without_bound_context(): void {
        $customer = $this->linkedCustomer();
        // Ein bezahlter Beleg ohne Zahldatum: der Zahlungsabgleich baut dafür den SDK-Client.
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-paid-1',
            'voucher_type' => 'salesinvoice',
            'voucher_status' => 'paid',
            'voucher_date' => '2026-10-01',
            'paid_date' => null,
        ]);
        $fake = $this->recordingHttp();
        $factory = new class extends LexofficeClientFactory {
            /** @var list<?int> */
            public array $organizations = [];

            public function for(?int $organizationId): Client {
                $this->organizations[] = $organizationId;

                return parent::for($organizationId);
            }
        };
        app()->instance(LexofficeClientFactory::class, $factory);
        // Die Queue vergisst den Organisationskontext vor jedem Job.
        app()->forgetInstance('currentOrganization');

        (new SyncOwnerVouchersJob($this->organization->id, 'customer', $customer->id))->withFakeQueueInteractions()->handle();

        // Belegliste und Zahlungen laufen über den SDK-Client der Organisation des Jobs, mit ihrem Abstand.
        $this->assertSame([], $fake->intervals, 'kein roher API-Client');
        $this->assertNotSame([], $factory->organizations);
        $this->assertSame([$this->organization->id], array_values(array_unique($factory->organizations)));
        $this->assertSame([1.25], array_values(array_unique($fake->sdkIntervals)));
    }

    /** Der SDK-Client bekommt Abstand, Budget und Weiterleitungsprüfung wie der API-Client; der Test-Transport bleibt ungedrosselt. */
    public function test_the_sdk_client_gets_interval_retry_budget_and_guarded_redirects(): void {
        // Echte Fabrik: eigener Transport, ohne dass eine Anfrage hinausgeht.
        app()->instance(PluginHttpFactory::class, new PluginHttpFactory);
        $factory = app(LexofficeClientFactory::class);

        $client = $factory->for($this->organization->id);
        $this->assertSame(1.25, $client->getRequestInterval(), 'Abstand aus der Einstellung der Organisation');
        $this->assertSame(LexofficeClientFactory::MAX_RETRIES, $client->getMaxRetries());
        $transport = (fn () => $this->client)->call($client);
        $this->assertInstanceOf(\Closure::class, $transport->getConfig('allow_redirects')['on_redirect'] ?? null, 'Weiterleitungen laufen durch die SSRF-Schranke');

        $this->setInterval('0.2');
        $this->assertSame(Client::MIN_INTERVAL, $factory->for($this->organization->id)->getRequestInterval(), 'nie unter der Untergrenze des SDK');
        $this->setInterval('0');
        $this->assertSame(0.0, $factory->for($this->organization->id)->getRequestInterval(), '0 schaltet die Drossel ab');

        $this->setInterval('1.25');
        FakePluginHttp::fake();
        $faked = $factory->for($this->organization->id);
        $this->assertSame(0.0, $faked->getRequestInterval());
        $this->assertSame(LexofficeClientFactory::MAX_RETRIES, $faked->getMaxRetries());
    }

    /** Ohne Schlüssel der Organisation baut die Fabrik keinen Client. */
    public function test_an_organization_without_key_gets_no_client(): void {
        $other = \App\Models\Platform\Organization::factory()->create();

        $this->assertFalse(app(LexofficeClientFactory::class)->isConfigured($other->id));
        $this->expectException(LexofficeNotConfiguredException::class);
        app(LexofficeClientFactory::class)->for($other->id);
    }

    private function setInterval(string $seconds): void {
        $setting = \App\Models\Platform\PluginSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $this->organization->id)->where('plugin_id', LexofficePlugin::ID)->firstOrFail();
        // settings ist verschlüsselt gespeichert — immer über das Modell schreiben.
        $setting->settings = ['api_key' => 'lex-key', 'request_interval' => $seconds];
        $setting->save();
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
