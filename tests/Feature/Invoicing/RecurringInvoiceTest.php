<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringInvoiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Models\Contract\Contract;
use App\Models\{Customer, Invoice, InvoiceSchedule, Organization, User};
use App\Services\Invoicing\RecurringInvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-415: Abrechnungspläne — Entwurfserzeugung mit Zeitraum-Platzhaltern,
 * Idempotenz je Periode, begrenzte Nachholung, Blocked-State bei externer
 * Rechnungshoheit, Vertrags-/Planende und Rechte-/Mandantengrenzen.
 */
class RecurringInvoiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

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
            'created_by' => $this->admin->id,
        ]);
    }

    private function makeSchedule(array $attributes = [], array $items = [['description' => 'Wartung {zeitraum_von} bis {zeitraum_bis}', 'quantity' => '1.000', 'unit' => 'pausch.', 'unit_price' => '250.0000']]): InvoiceSchedule {
        $schedule = InvoiceSchedule::create(array_merge([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'title' => 'Wartungsvertrag Monatlich',
            'interval_unit' => InvoiceSchedule::UNIT_MONTH,
            'interval_count' => 1,
            'billing_period_mode' => InvoiceSchedule::MODE_PREVIOUS,
            'next_run_on' => '2030-06-01',
            'status' => InvoiceSchedule::STATUS_ACTIVE,
            'created_by' => $this->admin->id,
        ], $attributes));

        $position = 0;
        foreach ($items as $item) {
            $schedule->items()->create(array_merge([
                'organization_id' => $this->organization->id,
                'position' => ++$position,
            ], $item));
        }

        return $schedule->fresh(['items', 'customer']);
    }

    public function test_due_schedule_creates_draft_with_period_placeholders(): void {
        $schedule = $this->makeSchedule();

        $result = app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));

        $this->assertSame(1, $result['created']);
        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        // Modus previous: Mai 2030.
        $this->assertSame('Wartung 01.05.2030 bis 31.05.2030', (string) $invoice->items->first()->description);
        $this->assertSame('250.00', $invoice->subtotal?->getAmount());

        $schedule->refresh();
        $this->assertSame('2030-07-01', $schedule->next_run_on->toDateString());
        $this->assertSame(1, $schedule->runs()->count());
    }

    public function test_generation_is_idempotent_per_period(): void {
        $this->makeSchedule();
        $service = app(RecurringInvoiceService::class);

        $service->generateDue(Carbon::parse('2030-06-01'));
        // Zweiter Lauf am selben Tag: Periode existiert bereits, Plan steht auf Juli.
        $service->generateDue(Carbon::parse('2030-06-01'));

        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_missed_runs_are_caught_up(): void {
        $schedule = $this->makeSchedule(['next_run_on' => '2030-03-01']);

        $result = app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));

        // März, April, Mai, Juni = 4 Entwürfe; nächster Lauf Juli.
        $this->assertSame(4, $result['created']);
        $this->assertSame(4, Invoice::query()->count());
        $this->assertSame('2030-07-01', $schedule->fresh()->next_run_on->toDateString());
    }

    public function test_external_billing_sovereignty_blocks_schedule(): void {
        $this->customer->update(['billing_mode' => 'lexoffice']);
        $schedule = $this->makeSchedule();

        $result = app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['blocked']);
        $this->assertSame(0, Invoice::query()->count());
        // Nicht aufholen, nicht weiterschalten — Plan bleibt sichtbar blockiert.
        $this->assertSame('2030-06-01', $schedule->fresh()->next_run_on->toDateString());
    }

    public function test_contract_end_ends_schedule(): void {
        $contract = Contract::create([
            'organization_id' => $this->organization->id,
            'number' => 'V-2029-001',
            'title' => 'Wartungsvertrag',
            'kind' => 'service',
            'status' => 'active',
            'starts_on' => '2029-01-01',
            'ends_on' => '2030-05-31',
        ]);
        $schedule = $this->makeSchedule(['contract_id' => $contract->id]);

        $result = app(RecurringInvoiceService::class)->generateDue(Carbon::parse('2030-06-01'));

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['ended']);
        $this->assertSame(InvoiceSchedule::STATUS_ENDED, $schedule->fresh()->status);
    }

    public function test_command_runs_and_reports(): void {
        $this->makeSchedule(['next_run_on' => now()->subDay()->toDateString()]);

        $this->artisan('invoices:generate-recurring')
            ->assertExitCode(0);

        $this->assertSame(1, Invoice::query()->withoutGlobalScopes()->count());
    }

    public function test_permissions_and_tenant_isolation(): void {
        $schedule = $this->makeSchedule();

        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)
            ->get(route('invoice-schedules.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('invoice-schedules.show', $schedule))
            ->assertOk();

        $foreignOrg = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $this->actingAs($foreignAdmin)
            ->get(route('invoice-schedules.show', $schedule))
            ->assertNotFound();
    }
}
