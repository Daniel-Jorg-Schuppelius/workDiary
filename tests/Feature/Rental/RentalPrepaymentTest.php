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

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Enums\Rental\{RentalCaseStatus, RentalRateCardStatus, RentalRequestStatus, RentalReservationStatus};
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, OnlinePayment};
use App\Models\Platform\User;
use App\Models\Rental\{RentalCase, RentalProfile, RentalRateCard, RentalRequest, RentalReservation};
use App\Services\Rental\RentalBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
}
