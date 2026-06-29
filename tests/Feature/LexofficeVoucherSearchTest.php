<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherSearchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\{Customer, LexofficeVoucher, PluginSetting, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Belegsuche (lexoffice.vouchers.index): zusätzlich zu Nummer/Typ auch nach
 * Firmenname (Kunde/Lieferant), Datumsstrings und Beträgen.
 */
final class LexofficeVoucherSearchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'test-key'],
        ]);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'BDM Haustechnik e.K.',
        ]);

        LexofficeVoucher::query()->create([
            'organization_id' => $this->organization->id, 'external_id' => 'voucher-1',
            'customer_id' => $this->customer->id, 'voucher_type' => 'salesinvoice', 'voucher_status' => 'open',
            'voucher_number' => 'RE/2026/1076', 'voucher_date' => '2026-06-29',
            'total_amount' => '167.08', 'currency' => 'EUR', 'archived' => false,
        ]);
    }

    private function search(string $q): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)
            ->withSession([
                'ui.daterange.preset' => 'custom',
                'ui.daterange.from' => '2026-01-01',
                'ui.daterange.to' => '2026-12-31',
            ])
            ->get(route('lexoffice.vouchers.index', ['q' => $q]));
    }

    public function test_search_by_company_name(): void {
        $this->search('Haustechnik')->assertOk()->assertSee('RE/2026/1076');
    }

    public function test_search_by_german_date(): void {
        $this->search('29.06.2026')->assertOk()->assertSee('RE/2026/1076');
    }

    public function test_search_by_month_and_year(): void {
        $this->search('06.2026')->assertOk()->assertSee('RE/2026/1076');
    }

    public function test_search_by_german_amount(): void {
        $this->search('167,08')->assertOk()->assertSee('RE/2026/1076');
    }

    public function test_search_excludes_non_matching(): void {
        $this->search('Sanitär GmbH')->assertOk()->assertDontSee('RE/2026/1076');
    }
}
