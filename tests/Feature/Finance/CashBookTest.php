<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CashBookTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{CashEntry, CashRegister, Customer, Invoice, User};
use App\Services\Finance\{CashBookService, GdpduExportService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-414 Kassenbuch: lückenlose seq_no + Hash-Kette, Append-only-Guard,
 * Storno-Gegenbuchung, Tagesabschluss-Festschreibung, Barzahlung von
 * Rechnungen, Rechte-/Mandantengrenzen und Z3-Sektion.
 */
class CashBookTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private CashRegister $register;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->register = CashRegister::create([
            'organization_id' => $this->organization->id,
            'name' => 'Hauptkasse',
            'currency' => 'EUR',
            'opening_balance' => '100.00',
            'opened_on' => '2030-01-01',
        ]);
    }

    private function record(array $overrides = []): CashEntry {
        return app(CashBookService::class)->record($this->register, array_merge([
            'booked_on' => '2030-06-01',
            'direction' => CashEntry::DIRECTION_IN,
            'amount' => '50.00',
            'purpose' => 'Barverkauf Material',
            'created_by' => (int) $this->admin->id,
        ], $overrides));
    }

    public function test_entries_get_sequential_numbers_and_hash_chain(): void {
        $first = $this->record();
        $second = $this->record(['direction' => CashEntry::DIRECTION_OUT, 'amount' => '20.00', 'purpose' => 'Porto']);

        $this->assertSame(1, $first->seq_no);
        $this->assertSame(2, $second->seq_no);
        $this->assertNotNull($first->hash);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertSame(130.00, app(CashBookService::class)->balance($this->register));
    }

    public function test_entries_are_append_only(): void {
        $entry = $this->record();

        $this->expectException(RuntimeException::class);
        $entry->update(['amount' => '999.00']);
    }

    public function test_reverse_creates_counter_entry_and_blocks_double_reversal(): void {
        $entry = $this->record();

        $reversal = app(CashBookService::class)->reverse($entry, 'Falscher Betrag', (int) $this->admin->id, Carbon::parse('2030-06-02'));

        $this->assertSame(CashEntry::DIRECTION_OUT, $reversal->direction);
        $this->assertSame((string) $entry->amount, (string) $reversal->amount);
        $this->assertSame($entry->id, $reversal->reversal_of_id);
        $this->assertSame(100.00, app(CashBookService::class)->balance($this->register));

        $this->expectException(InvalidArgumentException::class);
        app(CashBookService::class)->reverse($entry, 'Nochmal', (int) $this->admin->id, Carbon::parse('2030-06-03'));
    }

    public function test_closing_freezes_day_and_records_difference(): void {
        $this->record();
        $service = app(CashBookService::class);

        $closing = $service->closeDay($this->register, Carbon::parse('2030-06-01'), 145.00, 'Zählung', (int) $this->admin->id);

        $this->assertSame('150.00', (string) $closing->expected_balance);
        $this->assertSame('-5.00', (string) $closing->difference);

        // Buchung in den abgeschlossenen Tag wird abgelehnt.
        $this->expectException(InvalidArgumentException::class);
        $this->record(['booked_on' => '2030-06-01']);
    }

    public function test_cash_payment_marks_invoice_paid_on_full_cover(): void {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Barzahler GmbH',
            'currency' => 'EUR',
            'created_by' => $this->admin->id,
        ]);
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'R2030-0001',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => '2030-06-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'subtotal' => '100.00',
            'tax_amount' => '19.00',
            'total' => '119.00',
            'created_by' => $this->admin->id,
        ]);

        $this->record(['amount' => '119.00', 'invoice_id' => $invoice->id, 'purpose' => 'Barzahlung R2030-0001']);

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame('2030-06-01', $invoice->paid_on?->toDateString());
    }

    public function test_permissions_and_tenant_isolation(): void {
        $member = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)
            ->get(route('cash-registers.index'))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->get(route('cash-registers.show', $this->register))
            ->assertOk();

        $foreignOrg = \App\Models\Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $foreignOrg->id]);
        $this->actingAs($foreignAdmin)
            ->get(route('cash-registers.show', $this->register))
            ->assertNotFound();
    }

    public function test_z3_export_offers_cash_book_section(): void {
        $this->assertContains('cash_entries', app(GdpduExportService::class)->availableSections());
    }
}
