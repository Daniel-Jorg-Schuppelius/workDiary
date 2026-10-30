<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingTransferResaleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Reselling\{ResalePeriod, ResaleSubscription};
use App\Services\Finance\{BillingPositionBuilder, BillingTransferException, BillingTransferService};
use App\Services\Reselling\Register\{PeriodPlanner, ResalePeriodTransferSource};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Übergabekanal „Abos“ (Feature 185, MVP-1422): Abo-Perioden als Quelle des
 * Übergabedienstes — Zeilen, eingefrorene Positionen mit Artikel und
 * Leistungszeitraum, Stempel beim Übertragen, Freigabe beim Storno,
 * Reservierung durch bestätigte Übergaben; Dialog zeigt den Kanal nur mit
 * dem Recht `reselling.invoice`.
 */
class BillingTransferResaleTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-09-04');
        $this->accountant = $this->orgAdmin();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Badhaus Bäder GmbH', 'billing_mode' => BillingMode::Workdiary]);
    }

    /** @param array<string, mixed> $attributes */
    private function subscription(array $attributes = []): ResaleSubscription {
        $subscription = ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'provider' => 'qualityhosting', 'label' => 'Microsoft 365 Business Premium',
            'customer_id' => $this->customer->id, 'quantity' => 2, 'starts_on' => '2025-08-05', 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto',
            'sale_unit_price' => '247.20', 'currency' => 'EUR', 'status' => 'active',
        ], $attributes));
        (new PeriodPlanner)->sync($subscription);

        return $subscription;
    }

    public function test_periods_become_items_and_frozen_positions_and_are_stamped_on_transfer(): void {
        $subscription = $this->subscription();
        $service = app(BillingTransferService::class);

        $transfer = $service->createDraft($this->customer, TransferChannel::Resale, TransferTarget::File, ['from' => '2025-08-01', 'to' => '2026-09-30'], null, $this->accountant);
        $this->assertSame(2, $transfer->items->count(), 'zwei fällige Perioden (2025, 2026)');
        $this->assertSame(MorphMap::alias(ResalePeriod::class), $transfer->items->first()?->source_type);
        $this->assertSame('988.80', $transfer->total_amount, '2 Perioden × 2 Lizenzen × 247,20');

        $positions = app(BillingPositionBuilder::class)->build($transfer);
        $this->assertCount(2, $positions);
        $first = $positions->first();
        $this->assertSame(ResalePeriodTransferSource::SOURCE_KIND, $first?->source_kind);
        $this->assertSame('2025-08-05', $first?->service_from?->toDateString());
        $this->assertSame('2026-08-04', $first?->service_to?->toDateString());
        $this->assertStringContainsString('05.08.2025', (string) $first?->description);
        $this->assertSame('988.80', (string) $service->confirm($transfer, $this->accountant)->total_amount);
        $this->assertSame(2, $transfer->positions()->count(), 'beim Bestätigen eingefroren');

        // Zeitraumfilter und Auswahl: nur die 2025er Periode.
        $period2025 = $subscription->periods()->where('starts_on', '2025-08-05')->firstOrFail();
        $rows = app(ResalePeriodTransferSource::class)->collect($this->customer, ['from' => '2025-01-01', 'to' => '2025-12-31'], null);
        $this->assertSame([], $rows->all(), 'bestätigte Übergabe hält die Perioden — keine Quelle mehr frei');

        // Übertragen (ohne Ziel-Aufruf): Stempel mit der Übergabe als Kennung, Perioden bleiben offen.
        $service->markTransferred($transfer, null, null, $this->accountant);
        $period2025->refresh();
        $this->assertSame('transfer:' . $transfer->id, $period2025->draft_reference);
        $this->assertNotNull($period2025->draft_created_at);
        $this->assertStringContainsString('transfer:' . $transfer->id, (string) $period2025->note);
        $this->assertSame(\App\Enums\Reselling\PeriodStatus::Open, $period2025->status);
        try {
            $service->createDraft($this->customer, TransferChannel::Resale, TransferTarget::File, [], null, $this->accountant);
            $this->fail('gestempelte Perioden sind keine Quelle mehr');
        } catch (BillingTransferException $e) {
            $this->assertSame((string) __('finance.error.no_sources'), $e->getMessage());
        }

        // Storno gibt die Perioden frei.
        $service->cancel($transfer->fresh(), 'Test', $this->accountant);
        $this->assertNull($period2025->fresh()?->draft_reference);
        $again = $service->createDraft($this->customer, TransferChannel::Resale, TransferTarget::File, ['from' => '2025-01-01', 'to' => '2025-12-31'], [$period2025->id], $this->accountant);
        $this->assertSame(1, $again->items->count(), 'nur die gewählte Periode');
        $this->assertSame(TransferStatus::Draft, $again->status);
    }

    public function test_dialog_offers_the_channel_only_with_the_reselling_invoice_right(): void {
        $this->actingAs($this->accountant)->get(route('finance.transfers.create'))->assertOk()->assertSee(TransferChannel::Resale->label());
        $worker = $this->userWithRole('aussendienst');
        $this->actingAs($worker)->get(route('finance.transfers.create'))->assertStatus(403);
    }
}
