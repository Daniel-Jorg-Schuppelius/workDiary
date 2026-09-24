<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubFeePaymentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Club;

use App\Enums\Club\{ClubFeeClaimStatus, ClubFeePaymentSource};
use App\Enums\Finance\{MandateKind, MandateStatus, MatchStatus, PaymentRunStatus, TransactionDirection};
use App\Enums\User\UserRole;
use App\Mail\ClubFeeNoticeMail;
use App\Models\Club\{ClubFeeAccount, ClubFeeClaim, ClubFeeDunning, ClubFeePayment, ClubFeeTariff, ClubMember};
use App\Models\Finance\{BankAccount, BankStatement, BankTransaction, PaymentAllocation, SepaMandate};
use App\Models\Platform\User;
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\Billing\Sepa\PaymentRunService;
use App\Services\Club\{ClubFeePaymentService, ClubFeeRunService, ClubFeeService};
use App\Services\Finance\ReconciliationService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zahlungen und Einzug (Feature 159, MVP-851): Teil-/Familienzahlung korrekt,
 * Rücklastschrift öffnet den Restbetrag genau einmal, Export ist keine
 * Zahlung, Kernabrechnung ohne privates Paket; dazu Bankabgleich ohne
 * Doppelanrechnung, Mahnung mit Sperre und die Beitragsansicht im Portal.
 */
class ClubFeePaymentTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClubFeeAccount $account;

    private ClubFeeTariff $adults;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->adults = app(ClubFeeService::class)->createTariff($this->organization, ['name' => 'Erwachsene']);
        app(ClubFeeService::class)->saveRate($this->adults, ['valid_from' => '2026-01-01', 'interval' => 'monthly', 'amount' => '30,00', 'anchor_month' => 1, 'due_days' => 14, 'proration' => 'full']);
        $this->account = app(ClubFeeService::class)->createAccount($this->organization, ['name' => 'Familie Muster', 'email' => 'muster@example.test'], $this->admin);
    }

    private function payments(): ClubFeePaymentService {
        return app(ClubFeePaymentService::class);
    }

    private function runs(): ClubFeeRunService {
        return app(ClubFeeRunService::class);
    }

    /** Zwei Forderungen (30,00 je Mitglied und Monat) aus zwei Monatsläufen. @return list<ClubFeeClaim> */
    private function claims(int $members = 2, int $months = 1): array {
        for ($i = 0; $i < $members; $i++) {
            app(ClubFeeService::class)->assign($this->account, ClubMember::factory()->create(['joined_on' => '2026-01-01']), $this->adults, ['valid_from' => '2026-01-01']);
        }
        $claims = [];
        for ($m = 1; $m <= $months; $m++) {
            $claims[] = $this->runs()->release($this->runs()->prepare($this->organization, 2026, $m, $this->admin), $this->admin)->first();
        }

        return $claims;
    }

    public function test_partial_and_family_bulk_payments_are_allocated_by_due_date_and_overpayment_becomes_credit(): void {
        [$january, $february] = $this->claims(2, 2);
        $this->assertSame('60.00', $january->total->getAmount(), 'Zwei Mitglieder je 30,00 in einer Familienforderung.');

        $this->payments()->recordPayment($this->account, ['amount' => '40,00', 'paid_on' => '2026-02-05', 'method' => 'transfer', 'reference' => 'Muster Januar'], $this->admin);
        $this->assertSame(ClubFeeClaimStatus::PartiallyPaid, $january->refresh()->status);
        $this->assertSame('20.00', $january->openAmount()->getAmount());

        $created = $this->payments()->recordPayment($this->account, ['amount' => '100,00', 'paid_on' => '2026-02-20', 'method' => 'cash'], $this->admin);
        $this->assertCount(3, $created, 'Rest Januar, Februar, Guthaben.');
        $this->assertSame(ClubFeeClaimStatus::Paid, $january->refresh()->status);
        $this->assertSame(ClubFeeClaimStatus::Paid, $february->refresh()->status);
        $this->assertSame('20.00', $this->payments()->creditBalance($this->account)->getAmount(), 'Überzahlung wird Guthaben, keine doppelte Anrechnung.');
        $this->assertSame('0.00', $this->runs()->openAmountFor($this->account)->getAmount());

        $march = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 3, $this->admin), $this->admin)->first();
        $applied = $this->payments()->applyCredit($this->account, $this->admin);
        $this->assertCount(1, $applied);
        $this->assertSame('40.00', $march->refresh()->openAmount()->getAmount(), 'Guthaben 20,00 verrechnet.');
        $this->assertSame('0.00', $this->payments()->creditBalance($this->account)->getAmount());
        $this->assertSame(ClubFeeClaimStatus::PartiallyPaid, $march->status);

        try {
            $this->payments()->recordPayment($this->account, ['amount' => '10,00', 'paid_on' => '2026-03-01', 'method' => 'transfer', 'claim_id' => $january->id], $this->admin);
            $this->fail('Bezahlte Forderung nimmt keine gezielte Zahlung an.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('claim_id', $e->errors());
        }
    }

    public function test_chargeback_reopens_the_remainder_exactly_once_and_blocks_collection(): void {
        [$claim] = $this->claims(1);
        $payment = $this->payments()->recordPayment($this->account, ['amount' => '30,00', 'paid_on' => '2026-02-01', 'method' => 'sepa'], $this->admin)->first();
        $this->assertSame(ClubFeeClaimStatus::Paid, $claim->refresh()->status);

        $compensation = $this->payments()->chargeback($payment, 'Konto gedeckt?', $this->admin, '3,00');
        $this->assertSame('-30.00', $compensation->amount->getAmount());
        $this->assertSame(ClubFeeClaimStatus::Open, $claim->refresh()->status);
        $this->assertSame('30.00', $claim->openAmount()->getAmount());
        $this->assertNotNull($claim->collection_blocked_at, 'Erneuter Einzug erst nach Klärung.');
        $this->assertSame(1, ClubFeeClaim::query()->where('corrects_claim_id', $claim->id)->count(), 'Bankgebühr als eigene Nachforderung.');
        $this->assertSame('3.00', ClubFeeClaim::query()->where('corrects_claim_id', $claim->id)->firstOrFail()->total->getAmount());

        try {
            $this->payments()->chargeback($payment, 'nochmal', $this->admin);
            $this->fail('Dieselbe Zahlung darf nur einmal kompensiert werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment', $e->errors());
        }
        $this->assertSame('30.00', $claim->refresh()->openAmount()->getAmount(), 'Restbetrag öffnet sich genau einmal.');
        $this->assertSame(0, $this->payments()->collectionProposals($this->organization)->filter(fn(array $r): bool => $r['claim']->id === $claim->id && $r['blocked'] === null)->count());
        $this->payments()->unblockCollection($claim, $this->admin);
        $this->assertNull($claim->refresh()->collection_blocked_at);
    }

    public function test_bank_matching_recognizes_a_booked_payment_instead_of_paying_twice(): void {
        [$claim] = $this->claims(1);
        $this->payments()->recordPayment($this->account, ['amount' => '30,00', 'paid_on' => '2026-02-01', 'method' => 'transfer'], $this->admin);
        $this->assertSame(ClubFeeClaimStatus::Paid, $claim->refresh()->status);

        $statement = BankStatement::factory()->create(['organization_id' => $this->organization->id]);
        $transaction = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id, 'bank_statement_id' => $statement->id, 'amount' => '30.00', 'direction' => TransactionDirection::Credit,
            'booking_date' => '2026-02-02', 'purpose' => 'Vereinsbeitrag ' . $claim->number, 'extracted_refs' => [$claim->number], 'match_status' => MatchStatus::Unmatched,
        ]);
        app(ReconciliationService::class)->confirm($transaction, [['type' => ClubFeeClaim::class, 'id' => $claim->id, 'amount' => 30.00]], $this->admin);

        $this->assertSame(1, ClubFeePayment::query()->where('club_fee_claim_id', $claim->id)->count(), 'Bankabgleich erkennt die manuelle Zahlung wieder.');
        $this->assertSame($transaction->id, ClubFeePayment::query()->where('club_fee_claim_id', $claim->id)->firstOrFail()->bank_transaction_id);
        $this->assertSame('30.00', $claim->refresh()->paid_amount->getAmount(), 'Keine doppelte Anrechnung desselben Geldes.');
        $this->assertSame(ClubFeeClaimStatus::Paid, $claim->status);

        // Umgekehrt: Bankgutschrift ohne vorherige Buchung erzeugt die Zahlung.
        $second = $this->runs()->release($this->runs()->prepare($this->organization, 2026, 2, $this->admin), $this->admin)->first();
        $credit = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id, 'bank_statement_id' => $statement->id, 'amount' => '50.00', 'direction' => TransactionDirection::Credit,
            'booking_date' => '2026-03-02', 'purpose' => $second->number, 'extracted_refs' => [$second->number], 'match_status' => MatchStatus::Unmatched,
        ]);
        app(ReconciliationService::class)->confirm($credit, [['type' => ClubFeeClaim::class, 'id' => $second->id, 'amount' => 50.00]], $this->admin);
        $this->assertSame(ClubFeeClaimStatus::Paid, $second->refresh()->status);
        $this->assertSame('20.00', $this->payments()->creditBalance($this->account)->getAmount(), 'Überzahlung aus der Bank wird Guthaben.');

        // Rücklastschrift über den Bankabgleich: Zahlung kompensiert, Forderung offen, Einzug gesperrt.
        $original = PaymentAllocation::query()->where('bank_transaction_id', $credit->id)->firstOrFail();
        $return = BankTransaction::factory()->create([
            'organization_id' => $this->organization->id, 'bank_statement_id' => $statement->id, 'amount' => '-50.00', 'direction' => TransactionDirection::Debit,
            'booking_date' => '2026-03-05', 'is_reversal' => true, 'return_reason' => 'AC04', 'match_status' => MatchStatus::Unmatched,
        ]);
        app(ReconciliationService::class)->processReturn($return, $original, 'AC04', $this->admin);
        $this->assertSame(ClubFeeClaimStatus::Open, $second->refresh()->status);
        $this->assertNotNull($second->collection_blocked_at);
        $this->assertSame(2, ClubFeePayment::query()->where('source', ClubFeePaymentSource::Chargeback->value)->count(), 'Forderungsanteil und Guthabenanteil der Gutschrift werden je einmal kompensiert.');
        $this->assertSame('0.00', $this->payments()->creditBalance($this->account)->getAmount(), 'Das zurückgebuchte Guthaben ist weg.');
        $this->assertSame('30.00', $second->openAmount()->getAmount());

        // Aufhebung der ersten Zuordnung löst nur die Verknüpfung — die manuelle Zahlung bleibt.
        $first = PaymentAllocation::query()->where('bank_transaction_id', $transaction->id)->firstOrFail();
        app(ReconciliationService::class)->unmatch($first, $this->admin);
        $this->assertSame(ClubFeeClaimStatus::Paid, $claim->refresh()->status);
        $this->assertNull(ClubFeePayment::query()->where('club_fee_claim_id', $claim->id)->firstOrFail()->bank_transaction_id);
    }

    public function test_dunning_requires_overdue_and_respects_the_block_and_creates_fee_as_linked_claim(): void {
        Mail::fake();
        [$claim] = $this->claims(1);
        $claim->update(['due_on' => CarbonImmutable::today()->addDays(10)->toDateString()]);
        try {
            $this->payments()->dun($claim->refresh(), [], $this->admin);
            $this->fail('Nur überfällige Forderungen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('level', $e->errors());
        }
        $claim->update(['due_on' => CarbonImmutable::today()->subDays(10)->toDateString()]);
        $this->payments()->blockDunning($claim, 'strittig', $this->admin);
        try {
            $this->payments()->dun($claim->refresh(), [], $this->admin);
            $this->fail('Mahnsperre greift.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('level', $e->errors());
        }
        $this->payments()->unblockDunning($claim, $this->admin);

        $dunning = $this->payments()->dun($claim->refresh(), ['pay_until' => CarbonImmutable::today()->addDays(10)->toDateString(), 'fee' => '5,00', 'note' => 'Bitte überweisen'], $this->admin);
        $this->assertSame(1, $dunning->level);
        $this->assertSame(1, $claim->refresh()->dunning_level);
        $this->assertNotNull($dunning->fee_claim_id, 'Gebühr als verknüpfte Nachforderung.');
        $this->assertSame('5.00', ClubFeeClaim::query()->findOrFail($dunning->fee_claim_id)->total->getAmount());
        $this->assertSame('30.00', $claim->total->getAmount(), 'Ursprüngliche Forderung unverändert.');

        $this->payments()->sendDunning($claim, $dunning, 'muster@example.test', $this->admin);
        Mail::assertQueued(ClubFeeNoticeMail::class, fn(ClubFeeNoticeMail $mail): bool => $mail->dunningId === $dunning->id);
        $this->actingAs($this->admin)->get(route('club.fees.claims.dunning.pdf', [$claim, $dunning]))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->payments()->dun($claim->refresh(), [], $this->admin);
        $this->payments()->dun($claim->refresh(), [], $this->admin);
        try {
            $this->payments()->dun($claim->refresh(), [], $this->admin);
            $this->fail('Höchststufe erreicht.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('level', $e->errors());
        }
        $this->assertSame(3, ClubFeeDunning::query()->where('club_fee_claim_id', $claim->id)->count());
    }

    public function test_collection_run_reserves_claims_export_is_not_a_payment_and_receipt_books_once(): void {
        [$claim] = $this->claims(1);
        $claim->update(['due_on' => CarbonImmutable::today()->toDateString()]);
        $mandate = SepaMandate::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $this->account->customer_id, 'reference' => 'MNDT-MUSTER-1', 'kind' => MandateKind::Recurring->value,
            'status' => MandateStatus::Active->value, 'signed_on' => '2026-01-10', 'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'account_holder' => 'Familie Muster',
        ]);
        $bankAccount = BankAccount::factory()->create(['organization_id' => $this->organization->id]);
        Setting::set('finance.sepa_creditor_id', 'DE98ZZZ09999999999', SettingScope::Organization, $this->organization);

        $proposals = $this->payments()->collectionProposals($this->organization);
        $this->assertCount(1, $proposals);
        $this->assertSame($mandate->id, $proposals->first()['mandate']?->id);
        $this->assertNull($proposals->first()['blocked']);

        $run = $this->payments()->createCollectionRun($bankAccount, $this->admin, [$claim->id]);
        $this->assertSame(1, $run->items()->count());
        $this->assertSame($claim->number . '-1', $run->items()->firstOrFail()->end_to_end_id, 'Eindeutige Versuchsreferenz.');
        $this->assertNotNull($claim->refresh()->payment_run_item_id, 'Aktiver Versuch reserviert den Betrag.');
        $this->assertSame(ClubFeeClaimStatus::Open, $claim->status, 'Vorgemerkt ist nicht bezahlt.');
        $this->assertSame((string) __('club.fees.label.collection_reserved'), $this->payments()->collectionProposals($this->organization)->first()['blocked']);
        try {
            $this->payments()->createCollectionRun($bankAccount, $this->admin, [$claim->id]);
            $this->fail('Reservierte Forderung wird nicht erneut eingezogen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('claim_ids', $e->errors());
        }

        $cancelled = $this->payments()->cancelCollectionRun($run);
        $this->assertSame(PaymentRunStatus::Cancelled, $cancelled->status);
        $this->assertNull($claim->refresh()->payment_run_item_id);
        $run = $this->payments()->createCollectionRun($bankAccount, $this->admin, [$claim->id]);
        $this->assertSame($claim->number . '-2', $run->items()->firstOrFail()->end_to_end_id, 'Neuer Versuch, neue Referenz.');

        app(PaymentRunService::class)->release($run, $this->admin);
        if (FinancialFormatsSupport::isAvailable()) {
            app(PaymentRunService::class)->export($run->refresh(), $this->admin);
            $this->assertSame(ClubFeeClaimStatus::Open, $claim->refresh()->status, 'Export ist keine Zahlung.');
            $this->assertSame(1, $this->payments()->settleCollectionRun($run->refresh(), CarbonImmutable::today(), $this->admin));
            $this->assertSame(ClubFeeClaimStatus::Paid, $claim->refresh()->status);
            $this->assertNull($claim->payment_run_item_id);
            $this->assertSame(1, ClubFeePayment::query()->where('source', ClubFeePaymentSource::Sepa->value)->count());
        } else {
            $run->forceFill(['status' => PaymentRunStatus::Exported->value, 'exported_at' => now()])->save();
            $this->assertSame(ClubFeeClaimStatus::Open, $claim->refresh()->status, 'Export ist keine Zahlung.');
            $this->assertSame(1, $this->payments()->settleCollectionRun($run->refresh(), CarbonImmutable::today(), $this->admin));
            $this->assertSame(ClubFeeClaimStatus::Paid, $claim->refresh()->status);
        }
        $this->actingAs($this->admin)->get(route('club.fees.collections.index'))->assertOk();
    }

    public function test_core_fee_accounting_works_without_the_private_finance_package(): void {
        [$claim] = $this->claims(1);
        $this->payments()->recordPayment($this->account, ['amount' => '30,00', 'paid_on' => '2026-02-01', 'method' => 'transfer'], $this->admin);
        $this->assertSame(ClubFeeClaimStatus::Paid, $claim->refresh()->status);
        $this->actingAs($this->admin)->get(route('club.fees.collections.index'))->assertOk()->assertSee(__('club.fees.title.collections'));
        $this->assertIsBool(FinancialFormatsSupport::isAvailable(), 'Die Beitragsverwaltung hängt nicht hart am Finanzpaket.');
    }

    public function test_portal_shows_fees_only_to_the_explicit_payer_and_pages_and_rights_work(): void {
        [$claim] = $this->claims(1);
        $payer = $this->orgUser();
        $guardian = $this->orgUser();
        $treasurer = $this->userWithRole(UserRole::Buchhaltung->value);
        $lead = $this->userWithRole(UserRole::Teamleitung->value);
        $this->account->update(['user_id' => $payer->id]);

        $this->actingAs($payer)->get(route('club.my.index'))->assertRedirect(route('club.my.fees'));
        $this->actingAs($payer)->get(route('club.my.fees'))->assertOk()->assertSee($claim->number)->assertSee('30,00');
        $this->actingAs($payer)->get(route('club.my.fees.pdf', $claim))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($guardian)->get(route('club.my.fees'))->assertForbidden();

        $this->actingAs($treasurer)->get(route('club.fees.payments.create', $this->account))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.payments.create', [$this->account, $claim]))->assertOk()->assertSee('30.00');
        $this->actingAs($treasurer)->post(route('club.fees.payments.store', $this->account), ['amount' => '10,00', 'paid_on' => '2026-02-01', 'method' => 'cash', 'claim_id' => $claim->sqid])->assertRedirect(route('club.fees.accounts.show', $this->account));
        $this->assertSame(ClubFeeClaimStatus::PartiallyPaid, $claim->refresh()->status);
        $payment = ClubFeePayment::query()->firstOrFail();
        $this->actingAs($treasurer)->get(route('club.fees.accounts.show', $this->account))->assertOk()->assertSee('10,00');
        $this->actingAs($treasurer)->get(route('club.fees.claims.show', $claim))->assertOk()->assertSee(__('club.fees.card.payments'));
        $this->actingAs($treasurer)->get(route('club.fees.payments.chargeback.edit', [$this->account, $payment]))->assertOk();
        $this->actingAs($treasurer)->get(route('club.fees.accounts.settings.edit', $this->account))->assertOk();
        $this->actingAs($treasurer)->post(route('club.fees.accounts.settings', $this->account), ['user_id' => $guardian->sqid])->assertRedirect();
        $this->assertSame($guardian->id, $this->account->refresh()->user_id);
        $claim->update(['due_on' => CarbonImmutable::today()->subDays(5)->toDateString()]);
        $this->actingAs($treasurer)->get(route('club.fees.claims.dun.edit', $claim))->assertOk();
        $this->actingAs($treasurer)->post(route('club.fees.claims.dun', $claim), ['pay_until' => CarbonImmutable::today()->addDays(7)->toDateString()])->assertRedirect();
        $this->assertSame(1, $claim->refresh()->dunning_level);
        $this->actingAs($treasurer)->get(route('club.fees.claims.dunning.block.edit', $claim))->assertOk();
        $this->actingAs($treasurer)->post(route('club.fees.payments.chargeback', [$this->account, $payment]), ['reason' => 'Rückbuchung'])->assertRedirect();
        $this->assertSame(ClubFeeClaimStatus::Open, $claim->refresh()->status);

        $this->actingAs($lead)->get(route('club.fees.collections.index'))->assertForbidden();
        $this->actingAs($this->orgUser())->post(route('club.fees.payments.store', $this->account), ['amount' => '1', 'paid_on' => '2026-02-01', 'method' => 'cash'])->assertForbidden();
    }
}
