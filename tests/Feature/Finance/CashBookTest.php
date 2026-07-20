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

    /** Vollaudit 2026-07 (H14): Tagesabschlüsse sind append-only — die Buchungssperre hängt an dieser Zeile. */
    public function test_daily_closing_is_append_only(): void {
        $this->record();
        $closing = app(CashBookService::class)->closeDay($this->register, Carbon::parse('2030-06-01'), 150.00, null, (int) $this->admin->id);

        try {
            $closing->update(['closing_date' => '2030-05-01']);
            $this->fail('Update am Tagesabschluss wurde nicht abgewiesen.');
        } catch (RuntimeException) {
        }

        $this->expectException(RuntimeException::class);
        $closing->delete();
    }

    /** Vollaudit 2026-07 (M36): Ausgaben über den Bestand hinaus sind unzulässig — eine Barkasse wird nie negativ. */
    public function test_payout_beyond_balance_is_rejected(): void {
        $this->record(); // Bestand: 100 Anfangsbestand + 50 Einnahme = 150.

        try {
            $this->record(['direction' => CashEntry::DIRECTION_OUT, 'amount' => '150.01', 'purpose' => 'Zu viel']);
            $this->fail('Kassenfehlbetrag wurde nicht abgewiesen.');
        } catch (InvalidArgumentException) {
        }

        // Entnahme exakt bis 0 bleibt zulässig.
        $this->record(['direction' => CashEntry::DIRECTION_OUT, 'amount' => '150.00', 'purpose' => 'Restentnahme']);
        $this->assertSame(0.00, app(CashBookService::class)->balance($this->register));

        // Storno korrigiert Fehlbuchungen und ist bewusst NICHT saldo-geprüft.
        $income = CashEntry::query()->where('direction', CashEntry::DIRECTION_IN)->firstOrFail();
        app(CashBookService::class)->reverse($income, 'Fehlbuchung', (int) $this->admin->id, Carbon::parse('2030-06-02'));
        $this->assertSame(-50.00, app(CashBookService::class)->balance($this->register));
    }

    /** Vollaudit 2026-07 (M37): Beleg-Anhang am Eintrag — Upload beim Buchen, danach für niemanden löschbar. */
    public function test_entry_receipt_upload_is_append_only(): void {
        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('quittung.pdf', 12, 'application/pdf');

        $this->actingAs($this->admin)
            ->post(route('cash-registers.entries.store', $this->register), [
                'booked_on' => '2030-06-01',
                'direction' => 'in',
                'amount' => '25.00',
                'purpose' => 'Barverkauf mit Beleg',
                'receipt' => $file,
            ])
            ->assertRedirect(route('cash-registers.show', $this->register));

        $entry = CashEntry::query()->latest('id')->firstOrFail();
        $this->assertCount(1, $entry->attachments);
        $attachment = $entry->attachments->first();

        $this->actingAs($this->admin)
            ->delete(route('attachments.destroy', $attachment))
            ->assertForbidden();
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
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
        $sections = app(GdpduExportService::class)->availableSections();
        $this->assertContains('cash_entries', $sections);
        // Vollaudit 2026-07 (M38/H11): Kassensturz-Nachweis + Eingangs-E-Rechnungen.
        $this->assertContains('cash_daily_closings', $sections);
        $this->assertContains('incoming_einvoices', $sections);
    }
}
