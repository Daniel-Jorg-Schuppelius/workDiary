<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolCustomerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Peppol;

use App\Models\{Customer, Organization, PeppolParticipantLookup, User};
use App\Plugins\PeppolAccessPoint\PeppolAccessPointPlugin;
use App\Plugins\PluginManager;
use ERechnungToolkit\Contracts\DnsNaptrResolverInterface;
use ERechnungToolkit\Peppol\{DocumentTypeId, ParticipantId};
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakeNaptrResolver, FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Peppol-Empfängerdaten am Kunden (Feature 066, MVP-734): Pflege der
 * Teilnehmerkennung, Registrierungsprüfung und Mandantengrenze.
 */
class PeppolCustomerTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const SMP_BASE = 'https://smp.example.test';

    private const RECEIVER = '9930:DE987654321';

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME GmbH',
            'currency' => 'EUR',
            'peppol_participant_id' => self::RECEIVER,
            'created_by' => $this->admin->id,
        ]);

        $this->enablePluginFor($this->organization, PeppolAccessPointPlugin::ID, [
            'base_url' => 'https://ap.example.test/v1',
            'api_key' => 'geheim',
            'sender_participant_id' => '9930:DE123456789',
            'sml_zone' => \ERechnungToolkit\Enums\SmlZone::TEST->value,
        ]);
        app(PluginManager::class)->flushRuntimeCaches();
    }

    private function fakeSmp(bool $registered = true): void {
        $this->app->instance(DnsNaptrResolverInterface::class, new FakeNaptrResolver($registered ? [self::SMP_BASE] : []));

        $href = self::SMP_BASE . '/' . rawurlencode(ParticipantId::DEFAULT_SCHEME . '::' . self::RECEIVER)
            . '/services/' . DocumentTypeId::peppolBisBillingInvoice()->urlEncoded();

        FakePluginHttp::fake([
            self::SMP_BASE . '/*' => new Psr7Response(200, ['Content-Type' => 'application/xml'],
                '<ServiceGroup xmlns="http://busdox.org/serviceMetadata/publishing/1.0/">'
                . '<ParticipantIdentifier scheme="' . ParticipantId::DEFAULT_SCHEME . '">' . self::RECEIVER . '</ParticipantIdentifier>'
                . '<ServiceMetadataReferenceCollection><ServiceMetadataReference href="' . $href . '"/></ServiceMetadataReferenceCollection>'
                . '</ServiceGroup>'),
        ]);
    }

    public function test_invalid_participant_id_is_rejected_by_the_form(): void {
        $this->actingAs($this->admin)
            ->from(route('customers.show', $this->customer))
            ->put(route('customers.update', $this->customer), [
                'name' => 'ACME GmbH',
                'currency' => 'EUR',
                'peppol_participant_id' => 'DE987654321',
            ])
            ->assertSessionHasErrors('peppol_participant_id');
    }

    public function test_valid_participant_id_is_stored(): void {
        $this->actingAs($this->admin)
            ->put(route('customers.update', $this->customer), [
                'name' => 'ACME GmbH',
                'currency' => 'EUR',
                'peppol_participant_id' => '0204:991-12345-67',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('0204:991-12345-67', $this->customer->fresh()->peppol_participant_id);
    }

    public function test_registration_check_stores_the_smp_result(): void {
        $this->fakeSmp();

        $this->actingAs($this->admin)
            ->post(route('customers.peppol.check', $this->customer))
            ->assertSessionHas('status');

        $lookup = PeppolParticipantLookup::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertTrue($lookup->registered);
        $this->assertSame(self::SMP_BASE, $lookup->smp_base_url);
        $this->assertCount(1, $lookup->document_types ?? []);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $this->customer->getMorphClass(),
            'auditable_id' => $this->customer->id,
            'event' => 'customer.peppolChecked',
        ]);
    }

    public function test_registration_check_reports_an_unregistered_participant(): void {
        $this->fakeSmp(registered: false);

        $this->actingAs($this->admin)
            ->post(route('customers.peppol.check', $this->customer))
            ->assertSessionHas('error');

        $this->assertFalse((bool) PeppolParticipantLookup::query()->withoutGlobalScopes()->firstOrFail()->registered);
    }

    public function test_registration_check_without_participant_id_is_a_clear_message(): void {
        $this->customer->forceFill(['peppol_participant_id' => null])->save();

        $this->actingAs($this->admin)
            ->post(route('customers.peppol.check', $this->customer))
            ->assertSessionHas('error', fn (string $m): bool => str_contains($m, 'keine Peppol-Teilnehmer-ID'));

        $this->assertSame(0, PeppolParticipantLookup::query()->withoutGlobalScopes()->count());
    }

    public function test_customer_page_shows_the_participant_and_the_check_action(): void {
        $this->fakeSmp();
        $this->actingAs($this->admin)->post(route('customers.peppol.check', $this->customer));

        $response = $this->actingAs($this->admin)->get(route('customers.show', $this->customer));

        $response->assertOk();
        $response->assertSee(self::RECEIVER);
        $response->assertSee(route('customers.peppol.check', $this->customer));
        $response->assertSee(__('peppol.action.check'));
    }

    public function test_foreign_customer_is_not_reachable(): void {
        $foreign = Organization::factory()->create();
        $foreignCustomer = Customer::create([
            'organization_id' => $foreign->id,
            'name' => 'Fremd GmbH',
            'currency' => 'EUR',
            'peppol_participant_id' => self::RECEIVER,
        ]);

        $this->actingAs($this->admin)
            ->post(route('customers.peppol.check', $foreignCustomer))
            ->assertNotFound();
    }
}
