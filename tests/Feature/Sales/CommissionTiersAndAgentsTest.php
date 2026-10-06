<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionTiersAndAgentsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Sales\{CommissionReversalKind, CommissionScope, CommissionTierPeriod};
use App\Events\Invoicing\InvoicePaymentReceived;
use App\Models\Customer\Customer;
use App\Models\Finance\{BankStatement, BankTransaction, PaymentAllocation};
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionAgent, CommissionRule, InvoiceCommission};
use App\Services\Finance\ReconciliationService;
use App\Services\Invoicing\Contracts\PaymentStatusProvider;
use App\Services\Sales\{CommissionAccrualService, CommissionSettlementService};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-988/989: Staffeln, Jahresdeckel, Haftungsfrist, Teilzahlungen und externe Vermittler. */
final class CommissionTiersAndAgentsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $seller;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->seller = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Vera Vertrieb']);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH']);
    }

    /** @param array<string, mixed> $attributes */
    private function rule(array $attributes = []): CommissionRule {
        return CommissionRule::query()->create(array_replace([
            'organization_id' => $this->organization->id, 'name' => 'Grundsatz', 'scope' => CommissionScope::All,
            'rate_percent' => '5.00', 'currency' => 'EUR', 'priority' => 100, 'is_active' => true,
        ], $attributes));
    }

    private function invoice(string $net, array $attributes = []): Invoice {
        $gross = bcmul($net, '1.19', 2);

        return Invoice::query()->create(array_replace([
            'organization_id' => $this->organization->id, 'customer_id' => $this->customer->id,
            'number' => 'RE-' . fake()->unique()->numberBetween(1000, 9999), 'status' => InvoiceStatus::Issued,
            'type' => Invoice::TYPE_INVOICE, 'currency' => 'EUR', 'subtotal' => $net, 'tax_rate' => '19.00', 'total' => $gross,
            'issued_on' => Carbon::parse('2026-08-01'), 'sales_user_id' => $this->seller->id, 'created_by' => $this->admin->id,
        ], $attributes));
    }

    private function pay(Invoice $invoice, string $on): Invoice {
        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_on = Carbon::parse($on);
        $invoice->save();

        return $invoice->refresh();
    }

    public function test_tier_rate_follows_the_revenue_reached_in_the_period(): void {
        $rule = $this->rule(['tier_period' => CommissionTierPeriod::Month]);
        $rule->tiers()->create(['organization_id' => $this->organization->id, 'threshold_amount' => '1000.00', 'rate_percent' => '7.00']);
        $rule->tiers()->create(['organization_id' => $this->organization->id, 'threshold_amount' => '3000.00', 'rate_percent' => '10.00']);

        $this->pay($this->invoice('800.00'), '2026-08-05');
        $this->pay($this->invoice('500.00'), '2026-08-10');
        $this->pay($this->invoice('2000.00'), '2026-08-20');
        $this->pay($this->invoice('100.00'), '2026-09-02');

        $rates = InvoiceCommission::query()->orderBy('id')->get()->map(fn (InvoiceCommission $row): string => $row->rate_percent->getNumericValue())->all();
        $this->assertSame(['5.00', '7.00', '10.00', '5.00'], $rates, 'neuer Monat beginnt wieder unten');
        $this->assertSame('200.00', InvoiceCommission::query()->orderBy('id')->skip(2)->first()?->commission_amount->getAmount());
    }

    public function test_annual_cap_cuts_the_commission_and_a_cancellation_reverses_what_was_paid(): void {
        $this->rule(['annual_cap_amount' => '100.00']);
        $this->pay($this->invoice('1000.00'), '2026-08-05');
        $second = $this->pay($this->invoice('1500.00'), '2026-08-06');
        $this->pay($this->invoice('400.00'), '2026-08-07');

        $rows = InvoiceCommission::query()->orderBy('id')->get();
        $this->assertSame(['50.00', '50.00', '0.00'], $rows->map(fn (InvoiceCommission $row): string => $row->commission_amount->getAmount())->all());
        $this->assertStringContainsString('100,00', (string) $rows[1]->note);

        app(CommissionAccrualService::class)->onInvoiceCancelled($second);
        $reversal = InvoiceCommission::query()->where('reversal_of_id', $rows[1]->id)->sole();
        $this->assertSame('-50.00', $reversal->commission_amount->getAmount());
    }

    public function test_liability_period_moves_the_row_into_the_payable_period(): void {
        $this->rule(['liability_days' => 30]);
        $this->pay($this->invoice('1000.00'), '2026-08-15');
        $row = InvoiceCommission::query()->sole();
        $this->assertSame('2026-09-14', $row->payable_on?->toDateString());

        $settlement = app(CommissionSettlementService::class);
        $this->assertCount(0, $settlement->openCommissions($this->organization->id, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), CurrencyCode::Euro));
        $this->assertCount(1, $settlement->openCommissions($this->organization->id, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), CurrencyCode::Euro));
    }

    public function test_partial_payments_accrue_pro_rata_only_with_the_rule_flag(): void {
        $paid = '0';
        $this->app->instance(PaymentStatusProvider::class, new class($paid) implements PaymentStatusProvider {
            public function __construct(public string $paid) {}

            public function allocatedSum(Invoice $invoice): float {
                return (float) $this->paid;
            }
        });
        $provider = app(PaymentStatusProvider::class);
        $this->rule(['is_partial_accrual' => true]);
        $invoice = $this->invoice('1000.00');

        $provider->paid = '595.00';
        $invoice->status = InvoiceStatus::PartiallyPaid;
        $invoice->save();
        InvoicePaymentReceived::dispatch($invoice->refresh());
        InvoicePaymentReceived::dispatch($invoice);
        $this->assertSame(['500.00'], InvoiceCommission::query()->pluck('base_amount')->map(fn ($m): string => $m->getAmount())->all());

        $provider->paid = '1190.00';
        $this->pay($invoice, '2026-08-20');
        $this->assertSame(['500.00', '500.00'], InvoiceCommission::query()->orderBy('id')->pluck('base_amount')->map(fn ($m): string => $m->getAmount())->all());

        // Ohne Kennzeichen entsteht auf Teilzahlungen nichts.
        CommissionRule::query()->update(['is_partial_accrual' => false]);
        $other = $this->invoice('200.00');
        $other->status = InvoiceStatus::PartiallyPaid;
        $other->save();
        InvoicePaymentReceived::dispatch($other->refresh());
        $this->assertSame(0, InvoiceCommission::query()->where('invoice_id', $other->id)->count());
    }

    public function test_bank_reconciliation_now_triggers_the_paid_seam(): void {
        $this->rule();
        $invoice = $this->invoice('100.00');
        $tx = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'amount' => '119.00', 'currency' => 'EUR',
        ]);

        app(ReconciliationService::class)->confirm($tx, [['type' => Invoice::class, 'id' => $invoice->id, 'amount' => 119.00]], $this->admin);

        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->assertSame('5.00', InvoiceCommission::query()->sole()->commission_amount->getAmount());
    }

    public function test_reverted_payment_reverses_down_to_the_paid_share_and_a_new_payment_accrues_again(): void {
        $this->rule(['is_partial_accrual' => true]);
        $invoice = $this->invoice('1000.00');
        $reconciliation = app(ReconciliationService::class);
        [$first, $second] = [$this->transaction('595.00'), $this->transaction('595.00')];

        $reconciliation->confirm($first, [['type' => Invoice::class, 'id' => $invoice->id, 'amount' => 595.00]], $this->admin);
        $reconciliation->confirm($second, [['type' => Invoice::class, 'id' => $invoice->id, 'amount' => 595.00]], $this->admin);
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
        $this->assertSame('1000.00', $this->netBase($invoice));

        $reconciliation->unmatch(PaymentAllocation::query()->where('bank_transaction_id', $second->id)->sole(), $this->admin);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
        $this->assertSame('500.00', $this->netBase($invoice), 'zurück auf den bezahlten Anteil');
        $reversal = InvoiceCommission::query()->whereNotNull('reversal_of_id')->sole();
        $this->assertSame(CommissionReversalKind::Payment, $reversal->reversal_kind);
        $this->assertSame('-25.00', $reversal->commission_amount->getAmount());

        $reconciliation->unmatch(PaymentAllocation::query()->where('bank_transaction_id', $first->id)->sole(), $this->admin);
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
        $this->assertSame('0.00', $this->netBase($invoice));

        $reconciliation->confirm($this->transaction('1190.00'), [['type' => Invoice::class, 'id' => $invoice->id, 'amount' => 1190.00]], $this->admin);
        $this->assertSame('1000.00', $this->netBase($invoice), 'erneute Zahlung lässt die Provision wieder entstehen');

        // Ohne Teilzahlungs-Kennzeichen fällt die Provision bei fehlender Deckung ganz weg.
        CommissionRule::query()->update(['is_partial_accrual' => false]);
        $other = $this->invoice('200.00');
        $tx = $this->transaction('238.00');
        $reconciliation->confirm($tx, [['type' => Invoice::class, 'id' => $other->id, 'amount' => 238.00]], $this->admin);
        $reconciliation->unmatch(PaymentAllocation::query()->where('bank_transaction_id', $tx->id)->sole(), $this->admin);
        $this->assertSame('0.00', $this->netBase($other));
        $this->assertSame(0, InvoiceCommission::query()->where('invoice_id', $other->id)->where('status', '!=', 'reversed')->count(), 'nie gemeldet: beide Zeilen neutral');
    }

    private function transaction(string $amount): BankTransaction {
        return BankTransaction::factory()->create([
            'organization_id' => $this->organization->id,
            'bank_statement_id' => BankStatement::factory()->create(['organization_id' => $this->organization->id])->id,
            'amount' => $amount, 'currency' => 'EUR',
        ]);
    }

    private function netBase(Invoice $invoice): string {
        $rows = InvoiceCommission::query()->where('invoice_id', $invoice->id)->where('status', '!=', 'reversed')->get();

        return Money::sum($rows->map(fn (InvoiceCommission $row): Money => $row->base_amount)->all(), CurrencyCode::Euro)->getAmount();
    }

    public function test_external_agent_receives_commission_and_appears_in_the_settlement(): void {
        $agent = CommissionAgent::query()->create(['organization_id' => $this->organization->id, 'name' => 'Paul Partner', 'company' => 'Partner KG', 'is_active' => true]);
        $this->rule();
        $this->rule(['name' => 'Partner', 'scope' => CommissionScope::Agent, 'commission_agent_id' => $agent->id, 'rate_percent' => '8.00']);
        $invoice = $this->invoice('1000.00', ['sales_user_id' => null]);
        app(CommissionAccrualService::class)->assign($invoice, null, $agent);
        $this->pay($invoice, '2026-08-15');

        $row = InvoiceCommission::query()->sole();
        $this->assertNull($row->user_id);
        $this->assertSame($agent->id, $row->commission_agent_id);
        $this->assertSame('80.00', $row->commission_amount->getAmount());
        $this->assertSame('Paul Partner (Partner KG)', $row->recipientName());

        $settlement = app(CommissionSettlementService::class);
        $run = $settlement->createRun($this->organization, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), CurrencyCode::Euro);
        $this->assertSame('Paul Partner (Partner KG)', $settlement->perUser($settlement->rowsOf($run), CurrencyCode::Euro)[0]['user']);
        $this->assertStringContainsString('Paul Partner', $settlement->exportCsv($run));

        // Umzuordnung auf die Person rechnet die Vermittlerzeile zurück.
        app(CommissionAccrualService::class)->assign($invoice->refresh(), $this->seller);
        $this->assertSame(1, InvoiceCommission::query()->where('user_id', $this->seller->id)->whereNull('reversal_of_id')->count());
        $this->assertSame(0, (int) InvoiceCommission::query()->whereNull('user_id')->where('status', '!=', 'reversed')->count());
    }

    public function test_rule_tiers_and_agents_are_managed_over_http(): void {
        $this->actingAs($this->admin)->post(route('commission-agents.store'), ['name' => 'Anna Agentin', 'email' => 'anna@example.test'])->assertRedirect(route('commission-agents.index'));
        $agent = CommissionAgent::query()->sole();
        $this->actingAs($this->admin)->get(route('commission-agents.index'))->assertOk()->assertSee('Anna Agentin');

        $this->actingAs($this->admin)->post(route('commission-rules.store'), [
            'name' => 'Staffel', 'scope' => 'agent', 'commission_agent_id' => $agent->sqid, 'rate_percent' => '4', 'priority' => 100, 'is_active' => '1',
            'tier_period' => 'quarter', 'tiers' => [['threshold' => '5000', 'rate' => '6'], ['threshold' => '', 'rate' => ''], ['threshold' => '10000', 'rate' => '8']],
            'annual_cap_amount' => '2500', 'liability_days' => '60', 'is_partial_accrual' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('commission-rules.index'));
        $rule = CommissionRule::query()->sole();
        $this->assertSame(CommissionTierPeriod::Quarter, $rule->tier_period);
        $this->assertSame(['5000.00', '10000.00'], $rule->orderedTiers()->map(fn ($tier): string => $tier->threshold_amount->getAmount())->all());
        $this->assertSame('2500.00', $rule->annual_cap_amount?->getAmount());
        $this->assertSame(60, $rule->liability_days);
        $this->assertTrue($rule->is_partial_accrual);
        $this->actingAs($this->admin)->get(route('commission-rules.edit', $rule))->assertOk()->assertSee('10000.00');

        $invoice = $this->invoice('100.00');
        $this->actingAs($this->admin)->get(route('commissions.assign.form', $invoice))->assertOk()->assertSee('Anna Agentin');
        $this->actingAs($this->admin)->post(route('commissions.assign', $invoice), ['commission_agent_id' => $agent->sqid])->assertRedirect();
        $this->assertSame($agent->id, $invoice->refresh()->sales_agent_id);
        $this->assertNull($invoice->sales_user_id);
    }
}
