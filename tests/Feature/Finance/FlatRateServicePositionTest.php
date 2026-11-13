<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlatRateServicePositionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Finance\BillingTransfer;
use App\Models\Platform\User;
use App\Models\Project\{Project, ProjectTimeShortfall, ProjectTimeTarget};
use App\Models\Time\TimeEntry;
use App\Plugins\Lexoffice\Services\LexofficeMapper;
use App\Services\Finance\{BillingPositionBuilder, BillingTransferService};
use App\Services\Invoicing\{BlockPrice, InvoiceGenerator, ResolvedService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1678: Eine Leistung mit der Einheit „Pauschale“ rechnet je
 * Auftrag und Abrechnung eine Position mit Menge 1 zum Preis der Leistung —
 * in der Übergabe, im Rechnungslauf und im Lexoffice-Zeitbeleg. Die Zeiten
 * bleiben als Nachweis an der Position; Taktung und Auffüllung auf das Soll
 * gelten nur für Stunden.
 */
final class FlatRateServicePositionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const array PERIOD = ['from' => '2030-04-01', 'to' => '2030-04-30'];

    private User $accountant;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2030-04-20');
        $this->accountant = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->accountant);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => '90.00',
            'currency' => 'EUR',
            'billing_mode' => BillingMode::Workdiary,
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Heizungswartung',
            'status' => ProjectStatus::Active->value,
            'hourly_rate' => null,
            'is_default' => false,
            // Taktung gilt nur für Stunden.
            'billing_increment_minutes' => 60,
        ]);
    }

    public function test_three_bookings_of_one_order_become_one_flat_position_in_the_transfer(): void {
        $this->flatRule();
        $order = $this->order('Wartung Kessel 2');
        $entries = [
            $this->entry($order, '2030-04-02 08:00', '2030-04-02 09:10'),
            $this->entry($order, '2030-04-03 13:00', '2030-04-03 13:20'),
            $this->entry($order, '2030-04-04 10:00', '2030-04-04 12:45'),
        ];

        $positions = app(BillingPositionBuilder::class)->build($this->transfer());

        $this->assertCount(1, $positions);
        $position = $positions->firstOrFail();
        $this->assertSame(1.0, $position->quantityFloat());
        $this->assertSame('Pauschale', $position->unit_name);
        $this->assertSame(250.0, $position->unitPriceFloat());
        $this->assertSame(250.0, (float) $position->amount);
        $this->assertSame(BlockPrice::SOURCE_SERVICE, $position->price_source);
        $this->assertEqualsCanonicalizing(array_map(static fn (TimeEntry $e): int => (int) $e->id, $entries), $position->source_ids);
        $this->assertStringContainsString('Wartung Kessel 2', (string) $position->name);
        $this->assertSame('2030-04-02', $position->service_from?->toDateString());
        $this->assertSame('2030-04-04', $position->service_to?->toDateString());
    }

    public function test_each_order_gets_its_own_flat_position(): void {
        $this->flatRule();
        $first = $this->order('Wartung Kessel 1');
        $second = $this->order('Wartung Kessel 2');
        $this->entry($first, '2030-04-02 08:00', '2030-04-02 09:00');
        $this->entry($first, '2030-04-03 08:00', '2030-04-03 09:00');
        $this->entry($second, '2030-04-05 08:00', '2030-04-05 09:00');

        $positions = app(BillingPositionBuilder::class)->build($this->transfer());

        $this->assertCount(2, $positions);
        $this->assertSame([1.0, 1.0], $positions->map->quantityFloat()->all());
        $this->assertSame(500.0, round((float) $positions->sum('amount'), 2));
    }

    public function test_local_invoice_bills_one_flat_item_and_keeps_the_bookings_as_proof(): void {
        $this->flatRule();
        $order = $this->order('Wartung Kessel 2');
        $entries = collect([
            $this->entry($order, '2030-04-02 08:00', '2030-04-02 09:10'),
            $this->entry($order, '2030-04-03 13:00', '2030-04-03 13:20'),
            $this->entry($order, '2030-04-04 10:00', '2030-04-04 12:45'),
        ]);
        $generator = app(InvoiceGenerator::class);

        $preview = $generator->previewTimeEntries($this->customer, null, self::PERIOD);
        $this->assertCount(1, $preview['lines']);
        $this->assertTrue($preview['lines'][0]['flat']);
        $this->assertSame(250.0, $preview['lines'][0]['amount']);

        $invoice = $generator->fromTimeEntries($this->customer, null, self::PERIOD);

        $items = $invoice->items()->get();
        $this->assertCount(1, $items);
        $item = $items->firstOrFail();
        $this->assertEqualsWithDelta(1.0, (float) $item->quantity, 0.0001);
        $this->assertSame('Pauschale', $item->unit);
        $this->assertEqualsWithDelta(250.0, $item->netAmount()->toFloat(), 0.001);
        $this->assertStringContainsString('Wartung Kessel 2', (string) $item->description);
        $this->assertSame(['2030-04-02', '2030-04-04'], [$item->service_from?->toDateString(), $item->service_to?->toDateString()]);
        $this->assertEqualsCanonicalizing($entries->pluck('id')->all(), $item->timeEntries()->pluck('time_entries.id')->all());
        $this->assertSame(3, TimeEntry::query()->whereKey($entries->pluck('id'))->where('exported', true)->count());
    }

    public function test_hourly_service_keeps_hours_times_rate(): void {
        $this->flatRule(unit: 'Std.', price: null);
        $this->entry($this->order('Wartung Kessel 2'), '2030-04-02 08:00', '2030-04-02 09:10');

        $position = app(BillingPositionBuilder::class)->build($this->transfer())->firstOrFail();

        // 70 Minuten auf die Stunde getaktet: 2 h zum Kundensatz.
        $this->assertSame(2.0, $position->quantityFloat());
        $this->assertSame(90.0, $position->unitPriceFloat());
    }

    public function test_flat_service_without_price_stays_unpriced(): void {
        $this->flatRule(price: null);
        $this->entry($this->order('Wartung Kessel 2'), '2030-04-02 08:00', '2030-04-02 09:00');

        $position = app(BillingPositionBuilder::class)->build($this->transfer())->firstOrFail();

        $this->assertSame(1.0, $position->quantityFloat());
        $this->assertSame(BlockPrice::SOURCE_NONE, $position->price_source);
        $this->assertTrue($position->isUnpriced());
    }

    public function test_flat_service_suppresses_the_top_up_to_target_hours(): void {
        $this->flatRule();
        $this->project->update(['bills_target_time' => true]);
        ProjectTimeTarget::factory()->create(['project_id' => $this->project->id, 'weekday' => 1, 'target_minutes' => 480]);
        $this->entry($this->order('Wartung Kessel 2'), '2030-04-01 08:00', '2030-04-01 14:00');
        ProjectTimeShortfall::factory()->create(['project_id' => $this->project->id, 'user_id' => $this->accountant->id, 'date' => '2030-04-01', 'target_minutes' => 480, 'booked_minutes' => 360, 'shortfall_minutes' => 120]);

        $preview = app(InvoiceGenerator::class)->previewTimeEntries($this->customer, null, self::PERIOD);

        $this->assertSame(0, $preview['target']['count']);
        $this->assertCount(1, $preview['lines']);
        $this->assertCount(1, app(BillingPositionBuilder::class)->build($this->transfer()));
    }

    public function test_lexoffice_time_voucher_carries_one_flat_item_per_order(): void {
        $this->flatRule();
        $order = $this->order('Wartung Kessel 2');
        $this->entry($order, '2030-04-02 08:00', '2030-04-02 09:10');
        $this->entry($order, '2030-04-03 13:00', '2030-04-03 13:20');

        $payload = (new LexofficeMapper)->timeEntriesToVoucherPayload(
            $this->customer,
            TimeEntry::query()->with('project')->get(),
            CarbonImmutable::parse('2030-04-01'),
            CarbonImmutable::parse('2030-04-30'),
        );

        $this->assertCount(1, $payload['voucherItems']);
        $item = $payload['voucherItems'][0];
        $this->assertSame(1.0, (float) $item['quantity']);
        $this->assertSame('Pauschale', $item['unitName']);
        $this->assertSame(250.0, $item['unitPrice']['netAmount']);
    }

    public function test_unit_decides_between_hours_and_flat_rate(): void {
        // Nur „Pauschale“ (UN/ECE LS oder die Pauschal-Einheit der App) ist pauschal; Stk, Tag, km rechnen nach Dauer.
        foreach (['' => true, 'h' => true, 'Std.' => true, 'Stunden' => true, 'hour' => true, 'heure' => true, 'Beratungs-Stunde' => true,
            'Stk' => true, 'Tag' => true, 'km' => true, 'Monat' => true,
            'Pauschale' => false, 'pauschal' => false, 'LS' => false, 'pausch.' => false, 'flat' => false, 'forfait' => false, 'forf.' => false, 'global' => false] as $unit => $hourly) {
            $service = new ResolvedService(null, null, $unit === '' ? null : (string) $unit, null, null, null, 'service', ResolvedService::SOURCE_PROJECT_RULE);
            $this->assertSame($hourly, $service->isHourly(), 'Einheit „' . $unit . '“');
        }
    }

    private function flatRule(string $unit = 'Pauschale', ?float $price = 250.0): void {
        $this->project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'unit_name' => $unit,
            'net_unit_price' => $price,
        ]);
    }

    private function order(string $title): DiaryEntry {
        return DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->accountant->id,
            'project_id' => $this->project->id,
            'customer_id' => $this->customer->id,
            'title' => $title,
        ]);
    }

    private function entry(DiaryEntry $order, string $start, string $end): TimeEntry {
        $from = CarbonImmutable::parse($start);
        $to = CarbonImmutable::parse($end);

        return TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->accountant->id,
            'date' => $from->toDateString(),
            'started_at' => $from,
            'ended_at' => $to,
            'minutes' => (int) $from->diffInMinutes($to),
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Kessel geprüft',
        ]);
    }

    private function transfer(): BillingTransfer {
        return app(BillingTransferService::class)->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::File,
            self::PERIOD,
            null,
            $this->accountant,
        );
    }
}
