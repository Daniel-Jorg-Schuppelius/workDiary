<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalPrepaymentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Enums\Finance\{AccountingEntryStatus, AllocationKind, BillingMode, OpenItemStatus, ProfitDetermination, SettlementKind};
use App\Enums\Invoicing\{InvoiceStatus, OnlinePaymentStatus};
use App\Enums\Rental\{RentalCaseStatus, RentalChargeStatus, RentalRateCardStatus, RentalRequestStatus, RentalReservationStatus};
use App\Models\Accounting\{AccountingEntry, AccountingEntryLine, AccountingOpenItem, AccountingOpenItemSettlement};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Finance\{BankTransaction, PaymentAllocation};
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard, RentalRequest, RentalReservation};
use App\Services\Accounting\{AccountingProfileService, ChartOfAccountsTemplateService, FiscalYearService, JournalService, OpenItemService};
use App\Services\Accounting\Posting\Adapters\{OnlinePaymentAdapter, SalesInvoiceAdapter};
use App\Services\Accounting\Posting\{PostingInboxService, PostingProposal};
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService};
use App\Services\Rental\RentalBillingService;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\Support\{FakeOnlinePaymentProvider, InteractsWithPlugins};
use Tests\TestCase;

/**
 * Online-Vorauszahlung bei der Direktbuchung im Verleih (Feature 073,
 * MVP-1515): Zahlungslink über den Anbieter, bestätigt erst mit dem
 * Zahlungseingang, Frist für unbezahlte Reservierungen, Anrechnung auf die
 * spätere Rechnung.
 */
final class RentalPrepaymentTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

    private User $admin;

    private User $portalUser;

    private Asset $asset;

    private RentalProfile $profile;

    private FakeOnlinePaymentProvider $provider;

    /** @var array{from: string, to: string} */
    private array $period = ['from' => '2026-10-07 08:00', 'to' => '2026-10-09 17:00'];

    protected function setUp(): void {
        parent::setUp();
        Mail::fake();
        $this->travelTo('2026-10-05 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'email' => 'mieter@example.org']);
        $this->allowPortal($customer);
        $this->portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->asset = Asset::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Minibagger']);
        $card = RentalRateCard::query()->create(['organization_id' => $this->organization->id, 'name' => 'Standard', 'version' => 1, 'status' => RentalRateCardStatus::Active->value, 'created_by' => $this->admin->id]);
        $card->items()->createMany([
            ['organization_id' => $this->organization->id, 'kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'group_code' => 'bagger', 'amount' => '100.00', 'unit' => 'day'],
            ['organization_id' => $this->organization->id, 'kind' => 'cleaning', 'label' => 'Endreinigung', 'amount' => '50.00', 'unit' => 'flat'],
        ]);
        $this->profile = RentalProfile::query()->create(['organization_id' => $this->organization->id, 'asset_id' => $this->asset->id, 'is_rentable' => true, 'portal_bookable' => true, 'group_code' => 'bagger', 'default_rate_card_id' => $card->id]);

        $this->registerPlugins($this->provider = new FakeOnlinePaymentProvider);
        $this->enablePluginFor($this->organization, FakeOnlinePaymentProvider::ID);
        $this->organization->forceFill(['settings' => array_replace_recursive((array) $this->organization->settings, [
            'rental' => ['portal_direct_booking' => true, 'portal_prepayment_percent' => 50, 'prepayment_hold_minutes' => 30],
        ])])->save();
    }

    private function book(): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->portalUser, 'customer')->post(route('customer.rentals.requests.store'), $this->period + ['subject' => 'asset:' . $this->asset->sqid, 'booking' => 'direct']);
    }

    private function webhook(string $reference): void {
        $this->post(route('payments.webhook', FakeOnlinePaymentProvider::ID), ['id' => $reference])->assertOk();
    }

    public function test_direct_booking_waits_for_the_prepayment_and_holds_the_asset(): void {
        $this->actingAs($this->portalUser, 'customer')->get(route('customer.rentals.requests.index', $this->period))
            ->assertOk()
            ->assertSee('Vorauszahlung 50 %');

        $this->book()->assertRedirect('https://pay.example.com/pay_1');

        $request = RentalRequest::query()->sole();
        $this->assertSame(RentalRequestStatus::AwaitingPayment, $request->status);
        $this->assertNull($request->decided_at);
        $this->assertSame('2026-10-05 09:30:00', $request->payment_due_at?->format('Y-m-d H:i:s'));
        // 3 Tagessätze à 100 € + Endreinigung 50 € = 350 € netto, 416,50 € brutto, davon 50 %.
        $this->assertSame('208.25', $request->prepayment_amount?->getAmount());
        $this->assertSame('208.25', $this->provider->requests[0]->amount->getAmount());
        $this->assertStringContainsString('zahlung=' . $request->sqid, $this->provider->requests[0]->returnUrl);
        $this->assertSame(RentalCaseStatus::Reserved, RentalCase::query()->sole()->status);

        // Das Gerät bleibt für andere gesperrt, solange die Frist läuft.
        $this->book()->assertSessionHas('error');
        $this->assertSame(1, RentalRequest::query()->count());

        // Webhook ohne Zahlung beim Anbieter bestätigt nichts.
        $this->webhook('pay_1');
        $this->assertSame(RentalRequestStatus::AwaitingPayment, $request->refresh()->status);

        $this->provider->settle('pay_1');
        $this->webhook('pay_1');

        $this->assertSame(RentalRequestStatus::Accepted, $request->refresh()->status);
        $this->assertNotNull($request->decided_at);
        $this->assertSame(OnlinePaymentStatus::Paid, OnlinePayment::query()->sole()->status);
    }

    public function test_return_from_the_provider_confirms_without_waiting_for_the_webhook(): void {
        $this->book();
        $request = RentalRequest::query()->sole();
        $this->provider->settle('pay_1');

        $this->actingAs($this->portalUser, 'customer')
            ->get(route('customer.rentals.requests.index', ['zahlung' => $request->sqid]))
            ->assertOk()
            ->assertSee('Zahlung eingegangen — Ihre Buchung ist bestätigt.');

        $this->assertSame(RentalRequestStatus::Accepted, $request->refresh()->status);
    }

    public function test_unpaid_booking_expires_and_releases_the_asset(): void {
        $this->book();
        $request = RentalRequest::query()->sole();

        $this->travel(31)->minutes();
        $this->artisan('rental:expire-prepayments')->expectsOutputToContain('freigegeben: 1')->assertSuccessful();

        $this->assertSame(RentalRequestStatus::Expired, $request->refresh()->status);
        $this->assertSame(RentalCaseStatus::Cancelled, RentalCase::query()->findOrFail($request->rental_case_id)->status);
        $this->assertSame(RentalReservationStatus::Cancelled, RentalReservation::query()->findOrFail($request->rental_reservation_id)->status);

        // Eine Zahlung nach Ablauf bestätigt nichts mehr; die Leitung entscheidet.
        $this->provider->settle('pay_1');
        $this->webhook('pay_1');
        $this->assertSame(RentalRequestStatus::Expired, $request->refresh()->status);

        // Das Gerät ist wieder frei.
        $this->book()->assertRedirect('https://pay.example.com/pay_2');
    }

    public function test_payment_just_before_the_deadline_is_not_expired(): void {
        $this->book();
        $request = RentalRequest::query()->sole();
        $this->provider->settle('pay_1'); // bezahlt, Webhook kam nie an

        $this->travel(31)->minutes();
        $this->artisan('rental:expire-prepayments')->assertSuccessful();

        $this->assertSame(RentalRequestStatus::Accepted, $request->refresh()->status);
    }

    public function test_without_payment_provider_direct_booking_is_refused(): void {
        \App\Models\Platform\PluginSetting::query()->where('plugin_id', FakeOnlinePaymentProvider::ID)->update(['enabled' => false]);

        $this->book()->assertSessionHas('error', 'Die Direktbuchung ist gerade nicht möglich, weil keine Online-Zahlung eingerichtet ist. Bitte senden Sie eine Anfrage.');
        $this->assertSame(0, RentalRequest::query()->count());
        $this->assertSame(0, RentalCase::query()->count());
    }

    public function test_rental_item_without_prepayment_is_booked_immediately(): void {
        $this->profile->forceFill(['prepayment_percent' => 0])->save();

        $this->book()->assertRedirect(route('customer.rentals.requests.index'));

        $this->assertSame(RentalRequestStatus::Accepted, RentalRequest::query()->sole()->status);
        $this->assertSame([], $this->provider->requests);
    }

    public function test_paid_prepayment_is_credited_to_the_rental_invoice(): void {
        $this->book();
        $this->provider->settle('pay_1');
        $this->webhook('pay_1');
        $case = RentalCase::query()->sole();

        $billing = app(RentalBillingService::class);
        $this->assertSame('208.25', $billing->prepaidTotal($case)?->getAmount());
        $charge = $billing->addCharge($case, $this->admin, ['kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);
        $invoice = $billing->invoiceReleasedCharges($case->refresh(), $this->admin);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame($invoice->id, (int) OnlinePayment::query()->sole()->invoice_id);
        $this->assertSame(208.25, OnlinePayment::settledSumFor($invoice));
        $this->assertNull($billing->prepaidTotal($case));

        // Ausgestellt deckt die Vorauszahlung die Rechnung anteilig (357,00 € brutto, 208,25 € bezahlt).
        app(\App\Services\Invoicing\InvoiceIssueService::class)->issue($invoice);
        $this->assertSame(\App\Enums\Invoicing\InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
    }

    public function test_discarded_draft_invoice_releases_the_prepayment(): void {
        $this->book();
        $this->provider->settle('pay_1');
        $this->webhook('pay_1');
        $case = RentalCase::query()->sole();
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);
        $invoice = $billing->invoiceReleasedCharges($case->refresh(), $this->admin);
        $this->assertInstanceOf(Invoice::class, $invoice);

        $invoice->delete();

        $this->assertNull(OnlinePayment::query()->sole()->invoice_id);
        $this->assertSame('208.25', $billing->prepaidTotal($case)?->getAmount());
    }

    /** MVP-1521: Der Storno der lokalen Rechnung gibt Mietpositionen und Vorauszahlung frei. */
    public function test_cancelled_rental_invoice_releases_charges_and_prepayment(): void {
        $case = $this->paidCase();
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);
        $invoice = $billing->invoiceReleasedCharges($case->refresh(), $this->admin);
        $this->assertInstanceOf(Invoice::class, $invoice);

        $invoice->cancel('Falsche Laufzeit', $this->admin->id);

        $this->assertSame(RentalChargeStatus::Released, $charge->refresh()->status);
        $this->assertNull($charge->invoice_id);
        $this->assertNull(OnlinePayment::query()->sole()->invoice_id);
        $this->assertSame('208.25', $billing->prepaidTotal($case)?->getAmount());

        $next = $billing->invoiceReleasedCharges($case->refresh(), $this->admin);
        $this->assertInstanceOf(Invoice::class, $next);
        $this->assertSame($next->id, (int) OnlinePayment::query()->sole()->invoice_id);
        $this->assertSame($next->id, (int) $charge->refresh()->invoice_id);
    }

    /**
     * MVP-1523: Führt ein Buchhaltungsprogramm die Rechnungen, gilt die
     * Vorauszahlung mit der Übergabe als angerechnet — die Akte nennt den
     * Betrag für das Programm, eine weitere Übergabe zählt ihn nicht erneut.
     */
    public function test_external_billing_credits_the_prepayment_once(): void {
        $case = $this->paidCase();
        Customer::query()->whereKey($case->customer_id)->update(['billing_mode' => BillingMode::Lexoffice->value]);
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);

        $this->assertNull($billing->invoiceReleasedCharges($case->refresh(), $this->admin));

        $payment = OnlinePayment::query()->sole();
        $this->assertNull($payment->invoice_id);
        $this->assertNotNull($payment->credited_at);
        $this->assertNull($billing->prepaidTotal($case));
        $this->assertSame('208.25', $billing->creditedPrepaidTotal($case)?->getAmount());
        $this->assertSame(['mode' => 'lexoffice', 'count' => 1, 'prepaid' => '208.25', 'prepayments' => 1], $this->transferAudits($case)[0]);

        $this->actingAs($this->admin)->get(route('rental.show', $case))
            ->assertOk()
            ->assertDontSee('wird auf die Rechnung angerechnet')
            ->assertSee('Online-Vorauszahlung 208,25 € mit den Mietpositionen an das Buchhaltungsprogramm übergeben — dort auf die Rechnung anrechnen.');

        $extra = $billing->addCharge($case, $this->admin, ['kind' => 'cleaning', 'label' => 'Endreinigung', 'quantity' => 1, 'unit' => 'flat', 'unit_price' => 50]);
        $billing->releaseCharge($extra, $this->admin);
        $billing->invoiceReleasedCharges($case->refresh(), $this->admin);

        $this->assertSame(['mode' => 'lexoffice', 'count' => 1, 'prepaid' => null, 'prepayments' => 0], $this->transferAudits($case)[1]);
    }

    private function paidCase(): RentalCase {
        $this->book();
        $this->provider->settle('pay_1');
        $this->webhook('pay_1');

        return RentalCase::query()->sole();
    }

    /** @return list<array<string, mixed>> */
    private function transferAudits(RentalCase $case): array {
        return \App\Models\Audit\AuditLog::query()
            ->where('event', 'rental.chargesTransferred')
            ->where('auditable_type', \App\Support\MorphMap::stableKey(RentalCase::class))
            ->where('auditable_id', $case->id)
            ->orderBy('id')
            ->pluck('changes')
            ->map(static fn ($changes): array => (array) $changes)
            ->all();
    }

    public function test_customer_can_withdraw_an_unpaid_booking(): void {
        $this->book();
        $request = RentalRequest::query()->sole();

        $this->actingAs($this->portalUser, 'customer')->post(route('customer.rentals.requests.withdraw', $request))->assertSessionHas('success');

        $this->assertSame(RentalRequestStatus::Withdrawn, $request->refresh()->status);
        $this->assertSame(RentalCaseStatus::Cancelled, RentalCase::query()->sole()->status);
    }

    public function test_unreachable_provider_keeps_the_reservation_for_a_retry(): void {
        $this->provider->failCheckout = true;
        $this->book()->assertRedirect(route('customer.rentals.requests.index'))->assertSessionHas('error');
        $request = RentalRequest::query()->sole();
        $this->assertSame(RentalRequestStatus::AwaitingPayment, $request->status);

        $this->provider->failCheckout = false;
        $this->actingAs($this->portalUser, 'customer')
            ->post(route('customer.rentals.requests.pay', $request))
            ->assertRedirect('https://pay.example.com/pay_1');
    }

    // ── Mindest-Istversteuerung im lokalen Zweig (MVP-1542) ─────────────

    /** Lokale Buchhaltung mit der Vorlage SKR03 ab Jahresbeginn. */
    private function localAccounting(): void {
        $startsOn = CarbonImmutable::parse('2026-01-01');
        app(AccountingProfileService::class)->configure($this->organization, [
            'profit_determination' => ProfitDetermination::DoubleEntry,
            'base_currency' => CurrencyCode::Euro,
            'fiscal_year_start_month' => 1,
            'starts_on' => $startsOn,
            'note' => null,
        ]);
        app(FiscalYearService::class)->create($this->organization, $startsOn);
        app(AccountingProfileService::class)->activateLocal($this->organization, $this->admin);
        app(ChartOfAccountsTemplateService::class)->apply($this->organization, 'skr03', $startsOn);
    }

    private function postEntry(PostingProposal $proposal): AccountingEntry {
        $this->assertSame([], $proposal->blockers);

        return app(JournalService::class)->post(app(PostingInboxService::class)->prepare($this->organization, $proposal, $this->admin), $this->admin);
    }

    /** @return list<string> */
    private function journalLines(PostingProposal $proposal): array {
        return array_map(static fn ($line): string => $line->account->number . ' S ' . $line->debit . ' H ' . $line->credit, $proposal->lines);
    }

    /** Haben − Soll eines Kontos über alle festgeschriebenen Buchungen. */
    private function balance(string $number): string {
        $lines = AccountingEntryLine::query()
            ->where('organization_id', $this->organization->id)
            ->whereHas('account', fn ($query) => $query->where('number', $number))
            ->whereHas('entry', fn ($query) => $query->whereIn('status', [AccountingEntryStatus::Posted->value, AccountingEntryStatus::Reversed->value]))
            ->get();

        return NumberHelper::negatePrecise(NumberHelper::sumPrecise($lines->map(static fn (AccountingEntryLine $line): string => $line->signedAmount()->getAmount())->all(), 2));
    }

    /** Akte mit bezahlter Vorauszahlung (208,25 € am 05.10.), deren Anzahlung gebucht ist. */
    private function bookedAdvance(): RentalCase {
        $this->localAccounting();
        $case = $this->paidCase();
        $this->postEntry(app(OnlinePaymentAdapter::class)->proposalFor($this->organization, OnlinePayment::query()->sole()));

        return $case;
    }

    /** Rechnung über 3 Tagessätze (300 € netto, 357 € brutto), ausgestellt am 02.11. */
    private function issuedInvoice(RentalCase $case): Invoice {
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);
        $invoice = $billing->invoiceReleasedCharges($case->refresh(), $this->admin);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->travelTo('2026-11-02 10:00:00');

        return app(InvoiceIssueService::class)->issue($invoice)->refresh();
    }

    public function test_prepayment_before_the_local_invoice_books_an_advance_with_vat_on_the_payment_day(): void {
        $this->localAccounting();
        $this->paidCase();

        $proposal = app(OnlinePaymentAdapter::class)->proposalFor($this->organization, OnlinePayment::query()->sole());

        $this->assertSame([], $proposal->blockers);
        $this->assertSame('2026-10-05', $proposal->bookedOn->toDateString());
        // 208,25 € brutto = 175,00 € Anzahlung + 33,25 € Umsatzsteuer, fällig im Oktober.
        $this->assertSame(['1360 S 208.25 H 0.00', '1718 S 0.00 H 175.00', '1776 S 0.00 H 33.25'], $this->journalLines($proposal));
        $this->assertSame('33.25', $proposal->lines[2]->taxAmount);
    }

    public function test_the_invoice_resolves_the_advance_so_the_vat_arises_once(): void {
        $case = $this->bookedAdvance();
        $paymentEntry = AccountingEntry::query()->where('source_key', 'online_payment:' . OnlinePayment::query()->sole()->id)->sole();

        $invoice = $this->issuedInvoice($case);
        $proposal = app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice);

        $this->assertSame([], $proposal->blockers);
        $this->assertSame('2026-11-02', $proposal->bookedOn->toDateString());
        $this->assertSame([
            '1400 S 148.75 H 0.00',
            '8400 S 0.00 H 300.00',
            '1776 S 0.00 H 57.00',
            '1718 S 175.00 H 0.00',
            '1776 S 33.25 H 0.00',
        ], $this->journalLines($proposal));
        $this->assertSame('-33.25', $proposal->lines[4]->taxAmount);
        $entry = $this->postEntry($proposal);

        // Umsatzsteuer gesamt = Steuer der Rechnung: 33,25 € im Oktober, 23,75 € im November.
        $this->assertSame('57.00', $this->balance('1776'));
        $this->assertSame('0.00', $this->balance('1718'));
        $this->assertSame('148.75', AccountingOpenItem::query()->where('accounting_entry_id', $entry->id)->sole()->open_amount?->getAmount());
        // Die Zuordnung zur Rechnung ändert die gebuchte Anzahlung nicht.
        $payment = OnlinePayment::query()->sole();
        $this->assertSame($invoice->id, (int) $payment->invoice_id);
        $this->assertFalse(app(OnlinePaymentAdapter::class)->changedSincePosting($payment, $paymentEntry->refresh()));
        $this->assertFalse(app(SalesInvoiceAdapter::class)->changedSincePosting($invoice, $entry->refresh()));
    }

    /** MVP-1521/1542: Der Entwurfsstorno gibt die Vorauszahlung frei, die Anzahlung bleibt offen und wird mit der nächsten Rechnung aufgelöst. */
    public function test_cancelled_draft_keeps_the_advance_open_for_the_next_invoice(): void {
        $case = $this->bookedAdvance();
        $billing = app(RentalBillingService::class);
        $charge = $billing->addCharge($case, $this->admin, ['kind' => 'daily_rate', 'label' => 'Tagessatz Bagger', 'quantity' => 3, 'unit' => 'day', 'unit_price' => 100]);
        $billing->releaseCharge($charge, $this->admin);
        $billing->invoiceReleasedCharges($case->refresh(), $this->admin)?->cancel('Falsche Laufzeit', $this->admin->id);
        $this->assertNull(OnlinePayment::query()->sole()->invoice_id);
        $this->assertSame('175.00', $this->balance('1718'));

        $next = $billing->invoiceReleasedCharges($case->refresh(), $this->admin);
        $this->assertInstanceOf(Invoice::class, $next);
        $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, app(InvoiceIssueService::class)->issue($next)->refresh()));

        $this->assertSame('0.00', $this->balance('1718'));
        $this->assertSame('57.00', $this->balance('1776'));
    }

    /**
     * Erstattung nach dem Buchen: Zahlung und Rechnung kommen in den
     * Buchungseingang zurück; der Stornobeleg dreht die gebuchte Auflösung
     * zurück, die Anzahlung steht wieder offen, und die neu gebuchte Zahlung
     * nimmt sie mit der Erstattung zurück.
     */
    public function test_refund_and_cancellation_restore_and_reverse_the_advance(): void {
        $case = $this->bookedAdvance();
        $invoice = $this->issuedInvoice($case);
        $invoiceEntry = $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice));
        $payment = OnlinePayment::query()->sole();
        $paymentEntry = AccountingEntry::query()->where('source_key', 'online_payment:' . $payment->id)->sole();

        $this->provider->refund('pay_1', Money::of('208.25', CurrencyCode::Euro));
        $this->webhook('pay_1');
        $this->assertSame('208.25', $payment->refresh()->refunded_amount->getAmount());
        $this->assertTrue(app(OnlinePaymentAdapter::class)->changedSincePosting($payment, $paymentEntry->refresh()));
        $this->assertTrue(app(SalesInvoiceAdapter::class)->changedSincePosting($invoice->refresh(), $invoiceEntry->refresh()));
        $this->assertSame(\App\Enums\Invoicing\InvoiceStatus::Issued, $invoice->status);

        $cancellation = app(InvoiceGenerator::class)->cancellationFor($invoice, 'Miete entfällt', $this->admin->id);
        $cancellation = app(InvoiceIssueService::class)->issue($cancellation)->refresh();
        $this->assertNull($payment->refresh()->invoice_id);
        $proposal = app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $cancellation);
        $this->assertSame([
            '1400 S 0.00 H 148.75',
            '8400 S 300.00 H 0.00',
            '1776 S 57.00 H 0.00',
            '1718 S 0.00 H 175.00',
            '1776 S 0.00 H 33.25',
        ], $this->journalLines($proposal));
        $this->assertSame(['-57.00', '33.25'], [$proposal->lines[2]->taxAmount, $proposal->lines[4]->taxAmount]);
        $this->postEntry($proposal);
        $this->assertSame('175.00', $this->balance('1718'));
        $this->assertSame('33.25', $this->balance('1776'));

        app(JournalService::class)->reverse($paymentEntry->refresh(), 'Erstattung', $this->admin);
        $rebooked = app(OnlinePaymentAdapter::class)->proposalFor($this->organization, $payment->refresh());
        $this->assertSame([
            '1360 S 208.25 H 0.00',
            '1718 S 0.00 H 175.00',
            '1776 S 0.00 H 33.25',
            '1718 S 175.00 H 0.00',
            '1776 S 33.25 H 0.00',
            '1360 S 0.00 H 208.25',
        ], $this->journalLines($rebooked));
        $this->postEntry($rebooked);

        $this->assertSame('0.00', $this->balance('1718'));
        $this->assertSame('0.00', $this->balance('1776'));
    }

    /** Bestand vor MVP-1542: Wer schon über die Forderung gebucht hat, wird nicht doppelt aufgelöst. */
    public function test_postings_from_before_the_advance_keep_settling_the_receivable(): void {
        $this->localAccounting();
        $case = $this->paidCase();
        $invoice = $this->issuedInvoice($case);
        $payment = OnlinePayment::query()->sole();

        // Rechnung ohne Auflösung gebucht (wie vor MVP-1542): Die Zahlung gleicht die Forderung aus.
        $payment->forceFill(['invoice_id' => null])->saveQuietly();
        $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice));
        $payment->forceFill(['invoice_id' => $invoice->id])->saveQuietly();
        $proposal = app(OnlinePaymentAdapter::class)->proposalFor($this->organization, $payment);
        $this->assertSame(['1360 S 208.25 H 0.00', '1400 S 0.00 H 208.25'], $this->journalLines($proposal));
        $this->assertSame($invoice->id, $proposal->extra['settles_source_id'] ?? null);

        // Zahlung über die Forderung gebucht: Die Rechnung löst nichts auf.
        $this->postEntry($proposal);
        $this->assertSame(['1400 S 357.00 H 0.00', '8400 S 0.00 H 300.00', '1776 S 0.00 H 57.00'], $this->journalLines(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice)));
    }

    // ── Storno bei Teilzahlung aus der Vorauszahlung (MVP-1544, MVP-1545) ──

    /** Ältere offene Rechnung desselben Kunden über 100 € netto, gebucht am 20.10. */
    private function olderOpenInvoice(RentalCase $case): Invoice {
        $this->travelTo('2026-10-20 10:00:00');
        $invoice = app(InvoiceGenerator::class)->emptyDraft(Customer::query()->findOrFail($case->customer_id));
        $invoice->items()->create(['organization_id' => $this->organization->id, 'description' => 'Wartung Rüttelplatte', 'quantity' => '1', 'unit' => 'Psch.', 'unit_price' => '100.00', 'position' => 1]);
        $invoice->load('items');
        $invoice->recalculate();
        $invoice->save();
        $invoice = app(InvoiceIssueService::class)->issue($invoice->refresh())->refresh();
        $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice));

        return $invoice;
    }

    public function test_invoice_covered_only_by_the_prepayment_is_cancelled_by_a_cancellation_document(): void {
        $case = $this->bookedAdvance();
        $older = $this->olderOpenInvoice($case);
        $invoice = $this->issuedInvoice($case);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $invoiceEntry = $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice));
        $this->assertTrue($invoice->canBeCancelled());
        $this->assertNull($invoice->cancellationBlockedReason());

        $this->actingAs($this->admin)->post(route('invoices.cancel', $invoice), ['reason' => 'Miete entfällt'])->assertRedirect();

        $cancellation = Invoice::query()->where('type', Invoice::TYPE_CANCELLATION)->sole();
        $this->assertSame($invoice->id, (int) $cancellation->parent_invoice_id);
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->refresh()->status);
        $payment = OnlinePayment::query()->sole();
        $this->assertNull($payment->invoice_id);
        $this->assertSame('208.25', app(RentalBillingService::class)->prepaidTotal($case)?->getAmount());

        // Der Stornobeleg dreht die Auflösung zurück und gleicht genau den Rest der stornierten Rechnung aus.
        $cancellation = app(InvoiceIssueService::class)->issue($cancellation)->refresh();
        $proposal = app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $cancellation);
        $this->assertSame([
            '1400 S 0.00 H 148.75',
            '8400 S 300.00 H 0.00',
            '1776 S 57.00 H 0.00',
            '1718 S 0.00 H 175.00',
            '1776 S 0.00 H 33.25',
        ], $this->journalLines($proposal));
        $this->assertSame([$invoice->getMorphClass(), $invoice->id, SettlementKind::Cancellation->value], [
            $proposal->extra['settles_source_type'] ?? null, $proposal->extra['settles_source_id'] ?? null, $proposal->extra['settlement_kind'] ?? null,
        ]);
        $this->postEntry($proposal);

        $item = AccountingOpenItem::query()->where('accounting_entry_id', $invoiceEntry->id)->sole();
        $this->assertSame(OpenItemStatus::Settled, $item->status);
        $this->assertSame(SettlementKind::Cancellation, AccountingOpenItemSettlement::query()->where('accounting_open_item_id', $item->id)->sole()->kind);
        $olderItem = AccountingOpenItem::query()->where('source_type', $older->getMorphClass())->where('source_id', $older->id)->sole();
        $this->assertSame(OpenItemStatus::Open, $olderItem->status, 'Die ältere Rechnung des Kunden bleibt offen.');
        $this->assertSame('119.00', $olderItem->open_amount?->getAmount());
        // Die Anzahlung steht wieder offen; Umsatzsteuer nur aus Vorauszahlung (33,25 €) und älterer Rechnung (19,00 €).
        $this->assertSame('175.00', $this->balance('1718'));
        $this->assertSame('52.25', $this->balance('1776'));

        // Die Folgerechnung rechnet die Vorauszahlung wieder an und löst die Anzahlung auf.
        $next = app(RentalBillingService::class)->invoiceReleasedCharges($case->refresh(), $this->admin);
        $this->assertInstanceOf(Invoice::class, $next);
        $this->assertSame($next->id, (int) $payment->refresh()->invoice_id);
        $next = app(InvoiceIssueService::class)->issue($next)->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $next->status);
        $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $next));
        $this->assertSame('0.00', $this->balance('1718'));
        $this->assertSame('76.00', $this->balance('1776'));
    }

    /** @return array<string, array{0: string}> */
    public static function otherCoverage(): array {
        return [
            'Bankzuordnung' => ['allocation'],
            'Online-Zahlung der Rechnung' => ['online'],
            'Ausgleich am offenen Posten' => ['open_item'],
        ];
    }

    #[DataProvider('otherCoverage')]
    public function test_invoice_partly_paid_by_another_payment_stays_locked(string $other): void {
        $case = $other === 'open_item' ? $this->bookedAdvance() : $this->paidCase();
        $invoice = $this->issuedInvoice($case);
        $pay = [
            'allocation' => fn () => PaymentAllocation::query()->create([
                'organization_id' => $this->organization->id,
                'bank_transaction_id' => BankTransaction::factory()->create(['organization_id' => $this->organization->id, 'amount' => '50.00'])->id,
                'allocatable_type' => $invoice->getMorphClass(),
                'allocatable_id' => $invoice->id,
                'amount' => '50.00',
                'kind' => AllocationKind::Payment,
                'confirmed_at' => now(),
            ]),
            'online' => fn () => OnlinePayment::query()->create([
                'organization_id' => $this->organization->id, 'invoice_id' => $invoice->id, 'provider' => FakeOnlinePaymentProvider::ID,
                'provider_reference' => 'pay_rest', 'status' => OnlinePaymentStatus::Paid, 'currency' => 'EUR',
                'gross_amount' => '50.00', 'refunded_amount' => '0.00', 'paid_at' => now(),
            ]),
            'open_item' => fn () => app(OpenItemService::class)->settle(
                AccountingOpenItem::query()->where('accounting_entry_id', $this->postEntry(app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $invoice))->id)->sole(),
                SettlementKind::Retention,
                '20.00',
            ),
        ][$other];
        $pay();
        $invoice->refresh();
        $reason = (string) __('invoicing.cancellation.partially_paid');

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertFalse($invoice->canBeCancelled());
        $this->assertSame($reason, $invoice->cancellationBlockedReason());
        $this->actingAs($this->admin)->post(route('invoices.cancel', $invoice))->assertForbidden();
        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->assertSee($reason);
        try {
            app(InvoiceGenerator::class)->cancellationFor($invoice, 'Test', $this->admin->id);
            $this->fail('Der Storno muss gesperrt bleiben.');
        } catch (ValidationException $exception) {
            $this->assertSame([$reason], $exception->errors()['invoice'] ?? null);
        }

        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);
        $this->assertSame(0, Invoice::query()->where('type', Invoice::TYPE_CANCELLATION)->count());
        $this->assertSame($invoice->id, (int) OnlinePayment::query()->whereNotNull('subject_type')->sole()->invoice_id);
    }

    public function test_partial_refund_before_the_invoice_resolves_only_the_rest(): void {
        $case = $this->bookedAdvance();
        $this->provider->refund('pay_1', Money::of('89.25', CurrencyCode::Euro));
        $this->webhook('pay_1');
        $payment = OnlinePayment::query()->sole();
        app(JournalService::class)->reverse(AccountingEntry::query()->where('source_key', 'online_payment:' . $payment->id)->sole(), 'Erstattung', $this->admin);
        $this->postEntry(app(OnlinePaymentAdapter::class)->proposalFor($this->organization, $payment->refresh()));
        $this->assertSame('100.00', $this->balance('1718'));
        $this->assertSame('19.00', $this->balance('1776'));

        $proposal = app(SalesInvoiceAdapter::class)->proposalFor($this->organization, $this->issuedInvoice($case));

        $this->assertSame([
            '1400 S 238.00 H 0.00',
            '8400 S 0.00 H 300.00',
            '1776 S 0.00 H 57.00',
            '1718 S 100.00 H 0.00',
            '1776 S 19.00 H 0.00',
        ], $this->journalLines($proposal));
        $this->postEntry($proposal);
        $this->assertSame('0.00', $this->balance('1718'));
        $this->assertSame('57.00', $this->balance('1776'));
    }
}
