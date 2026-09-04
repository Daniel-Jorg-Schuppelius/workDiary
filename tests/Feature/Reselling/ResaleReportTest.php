<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Dashboard\Widgets\ResalePeriodsWidget;
use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\Customer;
use App\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Models\Reselling\{ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Register\PeriodPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Überblick (Feature 152, MVP-765): Margenbericht, Rechnungsvorschlag-CSV,
 * Dashboard-Kachel.
 */
class ResaleReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
    }

    public function test_report_export_and_widget_reflect_periods_and_links(): void {
        $admin = $this->orgAdmin();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Klimpel Bäder GmbH']);
        $subscription = ResaleSubscription::query()->create([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $customer->id, 'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'purchase_unit_price' => '187.92', 'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ]);
        (new PeriodPlanner)->sync($subscription);
        [$p2025, $p2026] = $subscription->periods;

        $voucher = LexofficeVoucher::create([
            'organization_id' => $this->organization->id, 'external_id' => 'inv-1', 'contact_external_id' => 'c-kl', 'voucher_type' => 'invoice', 'voucher_status' => 'paid',
            'voucher_number' => 'RE/2025/0820', 'voucher_date' => '2025-10-14', 'total_amount' => 494.4, 'currency' => 'EUR', 'archived' => false, 'lines_synced_at' => now(),
        ]);
        $line = LexofficeVoucherLine::create([
            'organization_id' => $this->organization->id, 'voucher_id' => $voucher->id, 'position' => 1, 'name' => 'Microsoft 365 Business Premium', 'quantity' => 24, 'unit_name' => 'Monat',
            'unit_net' => '20.60', 'total_net' => '494.40', 'tax_rate' => 19, 'currency' => 'EUR',
        ]);
        ResalePeriodLink::create([
            'organization_id' => $this->organization->id, 'period_id' => $p2025->id, 'subscription_id' => $subscription->id, 'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id,
            'voucher_number' => 'RE/2025/0820', 'voucher_date' => '2025-10-14', 'quantity' => 2, 'months' => 24, 'amount' => '494.40', 'currency' => 'EUR', 'origin' => LinkOrigin::Confirmed, 'confirmed_at' => now(),
        ]);
        $p2025->forceFill(['status' => PeriodStatus::Billed, 'decided_at' => now()])->save();

        $this->actingAs($admin)->get(route('finance.resale.report.index'))
            ->assertOk()
            ->assertSee('Microsoft 365 Business Premium')
            ->assertSee('Klimpel Bäder GmbH')
            ->assertSee('988,80 €')   // Soll-Verkauf 2 Perioden × 2 × 247,20
            ->assertSee('494,40 €')   // berechnet
            ->assertSee('751,68 €');  // Soll-Einkauf 2 × 2 × 187,92

        $csv = $this->actingAs($admin)->get(route('finance.resale.report.export'));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Klimpel Bäder GmbH', $body);
        $this->assertStringContainsString('05.08.2026 – 04.08.2027', $body, 'nur die offene Periode 2026');
        $this->assertStringNotContainsString('05.08.2025 – 04.08.2026', $body);
        $this->assertStringContainsString('24,00', $body);
        $this->assertStringContainsString('494,40', $body);

        $widget = new ResalePeriodsWidget;
        $this->assertTrue($widget->availableFor($admin));
        $this->assertFalse($widget->availableFor($this->orgUser()));
        $html = $widget->render($admin)->render();
        $this->assertStringContainsString(__('resale.widget.open'), $html);
        $this->assertStringContainsString('494,40', $html, 'offener Betrag der 2026er-Periode');
        $this->actingAs($this->orgUser())->get(route('finance.resale.report.index'))->assertForbidden();
    }
}
