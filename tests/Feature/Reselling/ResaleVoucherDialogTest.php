<?php
/*
 * Created on   : Tue Oct 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleVoucherDialogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Reselling\ResaleSubscription;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeArticle, LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Reselling\Register\{LicenseMonths, PeriodLinker, PeriodPlanner};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beleg hinter einem Rechnungsbezug (Nachbesserung 2026-10-27): die
 * Belegnummer in der Periodenzeile öffnet den Beleg mit Positionen,
 * Verbrauch und dem Vergleich zur Periode.
 */
class ResaleVoucherDialogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LexofficeArticle $exchange;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-27');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->exchange = LexofficeArticle::create([
            'organization_id' => $this->organization->id, 'external_id' => 'art-exo', 'name' => 'Exchange Online (Plan 1)', 'article_number' => 'ART-EXO',
            'type' => 'SERVICE', 'unit_name' => 'Monat', 'net_unit_price' => '3.95', 'currency' => 'EUR', 'vat_rate' => '19', 'synced_at' => now(),
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Thieme Transporte']);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-thieme', 'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->getKey(),
        ]);
    }

    public function test_voucher_dialog_shows_lines_consumption_and_the_comparison_with_the_period(): void {
        $admin = $this->orgAdmin();
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'external_id' => 'CNLCON00133', 'label' => 'Exchange Online Plan 1',
            'customer_id' => $this->customer->id, 'article_ref' => 'lex:' . $this->exchange->id, 'quantity' => 1, 'starts_on' => '2026-07-04',
            'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR', 'sale_unit_price' => '47.40',
        ]);
        (new PeriodPlanner)->sync($subscription);
        $period = $subscription->periods()->where('starts_on', '2026-07-04')->firstOrFail();
        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'v-1081', 'contact_external_id' => 'c-thieme', 'voucher_type' => 'invoice',
            'voucher_status' => 'paid', 'voucher_number' => 'RE/2026/1081', 'voucher_date' => '2026-07-04', 'total_amount' => 56.41, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
            'voucher_text' => 'Microsoft Dienste Thieme Transporte', 'service_starts_on' => '2026-07-04', 'service_ends_on' => '2027-07-03',
        ]);
        $line = LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'type' => 'service',
            'external_article_id' => 'art-exo', 'lexoffice_article_id' => $this->exchange->id, 'name' => 'Exchange Online (Plan 1)',
            'quantity' => 12, 'unit_name' => 'Monat', 'unit_net' => '3.95', 'total_net' => '47.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
        LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 2, 'type' => 'service',
            'name' => 'Business Support', 'quantity' => 1, 'unit_name' => 'Stück', 'unit_net' => '90.00', 'total_net' => '90.00', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
        (new PeriodLinker)->attach($period, $line, 12, null, $admin->id);

        // Die Periodenzeile verlinkt die Belegnummer auf den Dialog (Blade escaped das „&" im Attribut).
        $url = route('finance.resale.vouchers.show', ['source' => 'lexoffice', 'voucher' => $voucher->id, 'period' => $period->sqid, 'line' => $line->id]);
        $this->actingAs($admin)->get(route('finance.resale.show', $subscription->sqid))->assertOk()->assertSee(e($url), false);

        $dialog = $this->actingAs($admin)->get($url)->assertOk();
        $dialog->assertSee(__('resale.voucher.title', ['number' => 'RE/2026/1081']))
            ->assertSee('Microsoft Dienste Thieme Transporte')
            ->assertSee('Exchange Online (Plan 1)')->assertSee('Business Support')
            ->assertSee(__('resale.voucher.compare.title'))
            ->assertSee(__('resale.voucher.compare.sure'))
            ->assertSee(__('resale.voucher.compare.date_ok', ['days' => 0]))
            ->assertSee(__('resale.voucher.compare.service_ok'))
            ->assertSee(__('resale.voucher.compare.article_ok'))
            ->assertSee(__('resale.voucher.compare.line', ['line' => LicenseMonths::label(12.0, 12.0), 'raw' => '12 Monat', 'needed' => LicenseMonths::label(12.0, 12.0), 'covers' => LicenseMonths::label(12.0, 12.0)]))
            ->assertSee(__('resale.link.licence_months_only', ['months' => '12']))
            ->assertSee($period->label())
            ->assertSee(__('resale.voucher.none_consumed'), false);

        // Ohne Periode nur der Beleg; unbekannte Quelle oder Beleg → 404.
        $this->actingAs($admin)->get(route('finance.resale.vouchers.show', ['source' => 'lexoffice', 'voucher' => $voucher->id]))->assertOk()
            ->assertDontSee(__('resale.voucher.compare.title'));
        $this->actingAs($admin)->get(route('finance.resale.vouchers.show', ['source' => 'nirgends', 'voucher' => $voucher->id]))->assertNotFound();
        $this->actingAs($admin)->get(route('finance.resale.vouchers.show', ['source' => 'lexoffice', 'voucher' => 999999]))->assertNotFound();
    }
}
