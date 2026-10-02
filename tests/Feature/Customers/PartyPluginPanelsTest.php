<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PartyPluginPanelsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Customers;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Stammdaten\Contracts\ContactPushTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\TestCase;

/**
 * Kunden- und Lieferantenakte ohne Plugin-Wissen im Kern (MVP-1038/1039):
 * Lexoffice-Panel, Belegliste und Sammelaktion kommen aus dem Plugin.
 */
class PartyPluginPanelsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($this->customer);
        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'voucher-1',
            'customer_id' => $this->customer->id, 'voucher_type' => 'salesinvoice', 'voucher_status' => 'overdue',
            'voucher_number' => 'RE-LEX-1', 'voucher_date' => now()->toDateString(),
            'total_amount' => '100.00', 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    private function link(Customer|Supplier $party): void {
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $party->getMorphClass(),
            'referenceable_id' => $party->getKey(),
            'external_id' => 'lex-contact-' . $party->getKey(), 'synced_at' => now(),
        ]);
    }

    public function test_active_lexoffice_contributes_panel_documents_and_bulk_push(): void {
        $this->pluginSecret(LexofficePlugin::ID, ['api_key' => 'test-key']);

        $this->actingAs($this->admin)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertSee(__('Kunde als Kontakt in Lexoffice anlegen oder aktualisieren.'))
            ->assertSee('RE-LEX-1')
            ->assertSee(route('customers.lexoffice.sync-vouchers', $this->customer), false)
            ->assertSee(route('lexoffice.vouchers.dunning', LexofficeVoucher::query()->firstOrFail()), false);

        $this->actingAs($this->admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee(route('customers.lexoffice.push-all'), false);
    }

    public function test_without_active_plugin_the_core_pages_show_nothing_from_lexoffice(): void {
        $this->actingAs($this->admin)->get(route('customers.show', $this->customer))
            ->assertOk()
            ->assertDontSee(__('Kunde als Kontakt in Lexoffice anlegen oder aktualisieren.'))
            ->assertDontSee('RE-LEX-1')
            ->assertDontSee(route('customers.lexoffice.sync-vouchers', $this->customer), false);

        $this->actingAs($this->admin)->get(route('customers.index'))
            ->assertOk()
            ->assertDontSee(route('customers.lexoffice.push-all'), false);
    }

    public function test_supplier_panel_and_documents_come_from_the_plugin(): void {
        $this->pluginSecret(LexofficePlugin::ID, ['api_key' => 'test-key']);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->link($supplier);

        $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->assertSee(__('Kontakt verknüpft'))
            ->assertSee(route('suppliers.lexoffice.sync-vouchers', $supplier), false);
    }

    public function test_master_data_push_names_the_receiving_system(): void {
        $this->app->instance(ContactPushTarget::class, new class implements ContactPushTarget {
            public function pushAllowed(): bool {
                return true;
            }

            public function push(Customer $customer, string $pluginId): string {
                return 'lex-contact-1';
            }

            public function pushSupplier(Supplier $supplier, string $pluginId): string {
                return 'lex-contact-2';
            }
        });

        $this->actingAs($this->admin)->put(route('customers.update', $this->customer), [
            'name' => 'Neuer Name GmbH',
            'currency' => 'EUR',
        ])->assertRedirect()
            ->assertSessionHas('success', __('Kunde aktualisiert und an :system übertragen.', ['system' => 'Lexoffice']));
    }
}
