<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerLexofficeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Finance\BillingMode;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingRate};
use App\Models\{Customer, ExternalReference, Invoice, Project, TimeEntry, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Billing\{RetainerLexofficeService, RetainerRunner};
use App\Services\Invoicing\InvoiceGenerator;
use App\Support\Tz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 098 (Retainer-Modus): Monatspauschale + Spitzabrechnung werden als
 * NORMALE Lexoffice-Rechnung (finalize) übergeben; der lokale Leistungssaldo
 * bleibt erhalten. Guard-Skip nur im dedizierten Retainer-Pfad, Zeitenläufe
 * bleiben blockiert.
 */
class RetainerLexofficeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    private Project $project;

    private CustomerBillingAgreement $agreement;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('plugins.lexoffice.api_key', 'test-key');
        config()->set('plugins.lexoffice.base_url', 'https://api.lexoffice.io/v1');

        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'billing_mode' => BillingMode::Lexoffice->value,
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'mode' => 'retainer',
            'expected_monthly_amount' => 550.00,
            'workdays_per_week' => 6,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_billing_agreement_id' => $this->agreement->id,
            'day_type' => 'weekday',
            'hourly_rate' => 16.50,
        ]);

        // Kunde ist bereits ein Lexoffice-Kontakt (kein Contact-Push nötig).
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->id,
            'external_id' => 'contact-uuid-1',
            'synced_at' => now(),
        ]);
    }

    private function fakeInvoiceApi(string $uuid = 'lex-invoice-1', string $number = 'RE-2025-0001'): FakePluginHttp {
        return FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/invoices*' => function (RequestInterface $request) use ($uuid, $number) {
                if ($request->getMethod() === 'POST') {
                    return FakePluginHttp::response(['id' => $uuid], 201);
                }

                return FakePluginHttp::response([
                    'id' => $uuid,
                    'voucherNumber' => $number,
                    'voucherStatus' => 'open',
                ], 200);
            },
        ]);
    }

    private function makeEntry(string $day, int $hours = 2): TimeEntry {
        return TimeEntry::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'started_at' => $day . ' 08:00:00',
            'ended_at' => $day . ' ' . (8 + $hours) . ':00:00',
        ]);
    }

    public function test_push_monthly_retainer_creates_lexoffice_invoice_and_links_statement(): void {
        $this->fakeInvoiceApi();

        $invoice = app(RetainerLexofficeService::class)->pushMonthlyRetainer($this->agreement, 2026, 3);

        $this->assertNotNull($invoice);
        $this->assertSame(Invoice::TYPE_RETAINER, $invoice->type);
        $this->assertSame('RE-2025-0001', $invoice->number); // von Lexoffice übernommen
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame('550.0000', $invoice->items()->firstOrFail()->unit_price?->getAmount());

        $statement = $this->agreement->statements()->where('year', 2026)->where('month', 3)->firstOrFail();
        $this->assertSame($invoice->id, $statement->retainer_invoice_id);

        // ExternalReference-Anker für den Zahlstatus-Rücksync.
        $this->assertDatabaseHas('external_references', [
            'external_type' => 'invoice',
            'external_id' => 'lex-invoice-1',
            'referenceable_id' => $invoice->id,
        ]);
    }

    public function test_push_is_idempotent(): void {
        $this->fakeInvoiceApi();

        $first = app(RetainerLexofficeService::class)->pushMonthlyRetainer($this->agreement, 2026, 3);
        $second = app(RetainerLexofficeService::class)->pushMonthlyRetainer($this->agreement, 2026, 3);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Invoice::query()->where('type', Invoice::TYPE_RETAINER)->count());
    }

    public function test_push_is_blocked_when_a_lexoffice_voucher_is_already_linked(): void {
        $this->fakeInvoiceApi();
        // Für den Monat liegt die Rechnung schon in Lexoffice — ein Push legte
        // dort einen zweiten Beleg für dieselbe Pauschale an.
        $voucher = \App\Models\LexofficeVoucher::create([
            'organization_id' => $this->organization->id,
            'external_id' => 'lex-existing-1',
            'customer_id' => $this->customer->id,
            'voucher_type' => 'salesinvoice',
            'voucher_status' => 'paid',
            'voucher_number' => 'RE-2026-0099',
            'voucher_date' => '2026-03-31',
            'total_amount' => 654.50,
            'open_amount' => 0.00,
            'currency' => 'EUR',
            'archived' => false,
        ]);
        app(\App\Services\Billing\CustomerAccountStatementService::class)
            ->ensure($this->agreement, 2026, 3)
            ->update(['lexoffice_voucher_id' => $voucher->id]);

        $this->expectException(ValidationException::class);
        app(RetainerLexofficeService::class)->pushMonthlyRetainer($this->agreement, 2026, 3);
    }

    public function test_runner_pushes_previous_month_once(): void {
        $this->fakeInvoiceApi();
        $previous = Carbon::now(Tz::current())->startOfMonth()->subMonthNoOverflow();

        $result = app(RetainerRunner::class)->runDueForOrganization($this->organization);
        $this->assertSame(1, $result['created']);

        $second = app(RetainerRunner::class)->runDueForOrganization($this->organization);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);

        $this->assertSame(1, $this->agreement->statements()
            ->where('year', $previous->year)->where('month', $previous->month)
            ->whereNotNull('retainer_invoice_id')->count());
    }

    public function test_true_up_bills_open_balance(): void {
        // 10h Werktag à 16,50 = 165 Leistung, keine Zahlung ⇒ offener Saldo 165.
        $this->makeEntry('2026-03-02', 10);
        app(\App\Services\Billing\CustomerAccountStatementService::class)->recalculateOpen($this->agreement);
        $fake = $this->fakeInvoiceApi('lex-trueup-1', 'RE-2025-0009');

        $invoice = app(RetainerLexofficeService::class)->pushTrueUp($this->agreement);

        $this->assertSame(Invoice::TYPE_RETAINER, $invoice->type);
        $this->assertSame('165.0000', $invoice->items()->firstOrFail()->unit_price?->getAmount());
        $fake->assertSent(fn (RequestInterface $r) => str_contains((string) $r->getUri(), '/invoices'));
    }

    public function test_true_up_without_open_balance_is_rejected(): void {
        $this->fakeInvoiceApi();

        $this->expectException(ValidationException::class);
        app(RetainerLexofficeService::class)->pushTrueUp($this->agreement);
    }

    public function test_time_invoice_run_stays_blocked_for_retainer(): void {
        $this->makeEntry('2026-03-02', 4);

        // Retainer setzt billing_mode=lexoffice voraus — daher greift bereits
        // der Hoheits-Guard (extern geführt), noch vor dem Konto-Guard.
        $this->expectException(\App\Services\Finance\BillingModeLockedException::class);
        app(InvoiceGenerator::class)->fromTimeEntries($this->customer, null, []);
    }
}
