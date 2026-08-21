<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeterBillingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Metering;

use App\Models\{Asset, Customer, Invoice, MeterReading, Organization, User};
use App\Models\Metering\{MeterBillingAgreement, MeterBillingRun};
use App\Services\Metering\MeterBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zählerstands-Faktura (Feature 116, MVP-605).
 *
 * Der Kern: Der Lauf erzeugt **Entwürfe**, und eine fehlende Ablesung ist ein
 * Befund — keine Schätzung. Ein automatisch ausgestellter Beleg wäre nach GoBD
 * nicht mehr korrigierbar, während die häufigste Korrektur genau die
 * vergessene Ablesung ist.
 */
class MeterBillingTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    private Asset $asset;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->org->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function agreement(array $attributes = []): MeterBillingAgreement {
        return MeterBillingAgreement::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'asset_id' => $this->asset->id,
            'title' => 'Kopierer EG',
            'base_price' => '25.00',
            'unit_price' => '0.0120',
            'free_units' => '1000',
            'unit' => 'Klicks',
            'interval_unit' => 'monthly',
            'interval_count' => 1,
            'next_run_on' => '2026-06-30',
        ], $attributes));
    }

    private function reading(string $at, string $value, bool $estimated = false): MeterReading {
        return MeterReading::query()->create([
            'organization_id' => $this->org->id,
            'asset_id' => $this->asset->id,
            'read_at' => $at,
            'value' => $value,
            'unit' => 'Klicks',
            'is_estimated' => $estimated,
        ]);
    }

    public function test_draft_carries_base_price_and_billable_usage(): void {
        $agreement = $this->agreement();
        $this->reading('2026-05-31 10:00:00', '10000');
        $this->reading('2026-06-30 10:00:00', '13500');

        $invoice = app(MeterBillingService::class)->billPeriod(
            $agreement,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertNotNull($invoice);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status, 'Es entsteht NUR ein Entwurf.');
        $this->assertCount(2, $invoice->items);
        // 3500 Klicks − 1000 frei = 2500 × 0,0120 = 30,00 + 25,00 Grundpreis
        // = 55,00 netto; der Beleg trägt die vom TaxResolver ermittelte USt.
        $invoice->refresh();
        $this->assertSame('55.00', $invoice->subtotal?->getAmount());
        $this->assertSame('65.45', $invoice->total?->getAmount());
    }

    public function test_line_text_shows_consumption_and_free_units(): void {
        $agreement = $this->agreement();
        $this->reading('2026-05-31 10:00:00', '10000');
        $this->reading('2026-06-30 10:00:00', '13500');

        $invoice = app(MeterBillingService::class)->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $usage = $invoice->items->last();
        $this->assertStringContainsString('3.500', (string) $usage->description);
        $this->assertStringContainsString('1.000', (string) $usage->description);
    }

    /** Fehlende Ablesung ⇒ Befund, keine Schätzung. */
    public function test_missing_end_reading_skips_with_a_reason(): void {
        $agreement = $this->agreement();
        $this->reading('2026-05-31 10:00:00', '10000');

        $invoice = app(MeterBillingService::class)->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertNull($invoice);
        $this->assertSame(0, Invoice::query()->count());
        $this->assertSame('missing_end_reading', MeterBillingRun::query()->sole()->skipped_reason);
    }

    public function test_missing_start_reading_skips_with_a_reason(): void {
        $agreement = $this->agreement();
        $this->reading('2026-06-30 10:00:00', '13500');

        app(MeterBillingService::class)->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertSame('missing_start_reading', MeterBillingRun::query()->sole()->skipped_reason);
    }

    /** Zählerwechsel: negativer Verbrauch ist keine Gutschrift. */
    public function test_negative_consumption_is_refused(): void {
        $agreement = $this->agreement();
        $this->reading('2026-05-31 10:00:00', '13500');
        $this->reading('2026-06-30 10:00:00', '120');

        app(MeterBillingService::class)->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertSame('negative_consumption', MeterBillingRun::query()->sole()->skipped_reason);
        $this->assertSame(0, Invoice::query()->count());
    }

    /**
     * Der Verbrauch ist die Differenz zwischen letztem Stand VOR der Periode
     * und letztem Stand IN der Periode — nicht die Summe der
     * `consumption`-Felder, die auch den Zeitraum davor mitzählen würde.
     */
    public function test_consumption_ignores_the_period_before(): void {
        $agreement = $this->agreement(['free_units' => '0']);
        $this->reading('2026-04-30 10:00:00', '1000');
        $this->reading('2026-05-31 10:00:00', '5000');
        $this->reading('2026-06-30 10:00:00', '6000');

        [$consumption] = app(MeterBillingService::class)->consumptionFor(
            $agreement,
            CarbonImmutable::parse('2026-06-01'),
            CarbonImmutable::parse('2026-06-30'),
        );

        $this->assertSame(1000.0, $consumption);
    }

    /** Staffel rechnet STUFENWEISE — sonst senkte eine Einheit mehr die Rechnung. */
    public function test_tiers_are_calculated_progressively(): void {
        $agreement = $this->agreement([
            'free_units' => '0',
            'unit_price' => '0.0200',
            'tiers' => [
                ['from' => 0, 'price' => '0.0200'],
                ['from' => 1000, 'price' => '0.0100'],
            ],
        ]);

        $service = app(MeterBillingService::class);

        // 1500 = 1000 × 0,02 + 500 × 0,01 = 25,00
        $this->assertSame(25.0, round($service->priceFor($agreement, 1500.0), 2));
        // Monotonie: mehr Menge darf nie weniger kosten.
        $this->assertGreaterThan($service->priceFor($agreement, 999.0), $service->priceFor($agreement, 1001.0));
    }

    public function test_run_is_idempotent_per_period(): void {
        $agreement = $this->agreement();
        $this->reading('2026-05-31 10:00:00', '10000');
        $this->reading('2026-06-30 10:00:00', '13500');
        $service = app(MeterBillingService::class);

        $first = $service->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));
        $second = $service->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_scheduler_run_advances_the_next_date(): void {
        $agreement = $this->agreement(['next_run_on' => '2026-06-30']);
        $this->reading('2026-05-31 10:00:00', '10000');
        $this->reading('2026-06-30 10:00:00', '13500');

        app(MeterBillingService::class)->runAgreement($agreement, CarbonImmutable::parse('2026-07-01'));

        $this->assertSame('2026-07-30', $agreement->refresh()->next_run_on->toDateString());
        $this->assertSame('2026-06-30', $agreement->last_run_on?->toDateString());
    }

    public function test_command_reports_the_result(): void {
        $this->agreement(['next_run_on' => now()->subDay()->toDateString()]);

        $this->artisan('metering:generate-invoices')->assertExitCode(0);

        // Ohne Ablesungen: übersprungen, aber der Lauf ist protokolliert.
        $this->assertSame(1, MeterBillingRun::query()->count());
    }

    public function test_estimated_reading_is_marked_on_the_invoice(): void {
        $agreement = $this->agreement(['free_units' => '0']);
        $this->reading('2026-05-31 10:00:00', '10000');
        $this->reading('2026-06-30 10:00:00', '13500', estimated: true);

        $invoice = app(MeterBillingService::class)->billPeriod($agreement, CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertStringContainsString((string) __('metering.line.estimated'), (string) $invoice->items->last()->description);
    }

    public function test_index_lists_agreements_and_skipped_runs(): void {
        $this->agreement();
        MeterBillingRun::query()->create([
            'organization_id' => $this->org->id,
            'meter_billing_agreement_id' => MeterBillingAgreement::query()->sole()->id,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'skipped_reason' => 'missing_end_reading',
        ]);

        $response = $this->actingAs($this->admin)->get(route('metering.index'));

        $response->assertOk();
        $this->assertCount(1, $response->viewData('agreements'));
        $this->assertCount(1, $response->viewData('skipped'));
    }

    public function test_endpoint_creates_from_sqid_inputs(): void {
        $this->actingAs($this->admin)->post(route('metering.store'), [
            'customer_id' => $this->customer->sqid,
            'asset_id' => $this->asset->sqid,
            'title' => 'Drucker OG',
            'base_price' => '10',
            'unit_price' => '0.02',
            'free_units' => '0',
            'interval_unit' => 'monthly',
            'next_run_on' => '2026-07-31',
        ])->assertRedirect(route('metering.index'));

        $agreement = MeterBillingAgreement::query()->sole();
        $this->assertSame((int) $this->asset->id, (int) $agreement->asset_id);
    }

    public function test_non_billing_user_is_forbidden(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->org->id]);

        $this->actingAs($user)->get(route('metering.index'))->assertForbidden();
    }
}
