<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Invoicing;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Customer\Customer;
use App\Models\Invoicing\{Invoice, InvoicePaymentLink, OnlinePayment};
use App\Models\Platform\Organization;
use App\Services\Invoicing\DunningService;
use App\Services\Invoicing\OnlinePayment\InvoicePaymentLinkService;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\{FakeOnlinePaymentProvider, InteractsWithPlugins};
use Tests\TestCase;

/** MVP-1067: Zahlungslink, Bezahlseite über den offenen Betrag, Abgleich nur über die Nachfrage beim Anbieter. */
final class OnlinePaymentTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private FakeOnlinePaymentProvider $provider;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'email' => 'kunde@example.com']);
        $this->registerPlugins($this->provider = new FakeOnlinePaymentProvider);
        $this->enablePluginFor($this->org, FakeOnlinePaymentProvider::ID);
    }

    /** @param array<string, mixed> $overrides */
    private function invoice(array $overrides = []): Invoice {
        return Invoice::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R-2026-0100',
            'status' => Invoice::STATUS_ISSUED,
            'type' => Invoice::TYPE_INVOICE,
            'tax_rate' => '19.00',
            'total' => '119.00',
            'issued_on' => now()->subDays(3),
            'due_on' => now()->addDays(11),
        ], $overrides));
    }

    private function token(Invoice $invoice): string {
        $url = app(InvoicePaymentLinkService::class)->urlFor($invoice);
        $this->assertNotNull($url);

        return basename((string) parse_url((string) $url, PHP_URL_PATH));
    }

    private function eur(string $amount): Money {
        return Money::of($amount, CurrencyCode::Euro);
    }

    public function test_link_is_stable_and_leads_to_checkout_over_open_amount(): void {
        $invoice = $this->invoice();
        $token = $this->token($invoice);
        $this->assertSame($token, $this->token($invoice), 'PDF, Mail und Portal rendern denselben Link');
        $this->assertSame(1, InvoicePaymentLink::query()->count());

        $response = $this->get(route('payments.show', $token));
        $response->assertRedirect('https://pay.example.com/pay_1');
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame('119.00', $this->provider->requests[0]->amount->getAmount());
        $this->assertSame('kunde@example.com', $this->provider->requests[0]->customerEmail);

        // Zweiter Klick: dieselbe, noch gültige Bezahlseite.
        $this->get(route('payments.show', $token))->assertRedirect('https://pay.example.com/pay_1');
        $this->assertCount(1, $this->provider->requests);
    }

    public function test_webhook_settles_invoice_only_after_asking_the_provider(): void {
        $invoice = $this->invoice();
        $this->get(route('payments.show', $this->token($invoice)));

        // Webhook ohne Zahlung beim Anbieter: nichts gebucht.
        $this->post(route('payments.webhook', FakeOnlinePaymentProvider::ID), ['id' => 'pay_1'])->assertOk();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->refresh()->status);

        $this->provider->settle('pay_1', $this->eur('2.04'));
        $this->post(route('payments.webhook', FakeOnlinePaymentProvider::ID), ['id' => 'pay_1'])->assertOk();

        $payment = OnlinePayment::query()->firstOrFail();
        $this->assertSame(OnlinePaymentStatus::Paid, $payment->status);
        $this->assertSame('2.04', $payment->fee_amount?->getAmount());
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertNotNull($invoice->paid_on);
        $this->assertSame('0.00', app(DunningService::class)->openAmount($invoice)->getAmount());

        // Bezahlt: der Link zeigt keine Bezahlseite mehr.
        $this->get(route('payments.show', InvoicePaymentLink::query()->firstOrFail()->token))
            ->assertOk()->assertSee(__('payments.page.paid'));
    }

    public function test_amount_mismatch_and_unknown_webhooks_book_nothing(): void {
        $invoice = $this->invoice();
        $this->get(route('payments.show', $this->token($invoice)));

        $this->provider->settle('pay_1', amount: $this->eur('1.00'));
        $this->post(route('payments.webhook', FakeOnlinePaymentProvider::ID), ['id' => 'pay_1'])->assertOk();
        $this->assertSame(OnlinePaymentStatus::Open, OnlinePayment::query()->firstOrFail()->status);
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->refresh()->status);

        $this->post(route('payments.webhook', FakeOnlinePaymentProvider::ID), ['id' => 'pay_999'])->assertOk();
        $this->post(route('payments.webhook', 'unbekannt'), ['id' => 'pay_1'])->assertOk();
    }

    public function test_partial_coverage_new_checkout_and_refund_reverts(): void {
        $invoice = $this->invoice();
        $token = $this->token($invoice);
        $this->get(route('payments.show', $token));
        $this->provider->settle('pay_1');

        // Rückkehr vom Anbieter fragt selbst nach — ohne auf den Webhook zu warten.
        $this->get(route('payments.done', $token))->assertOk()->assertSee(__('payments.page.paid'));
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);

        $this->provider->refund('pay_1', $this->eur('19.00'));
        $this->post(route('payments.webhook', FakeOnlinePaymentProvider::ID), ['id' => 'pay_1']);
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->refresh()->status);
        $this->assertNull($invoice->paid_on);
        $this->assertSame('19.00', app(DunningService::class)->openAmount($invoice)->getAmount());

        // Neue Bezahlseite nur über den Rest.
        $this->get(route('payments.show', $token))->assertRedirect('https://pay.example.com/pay_2');
        $this->assertSame('19.00', $this->provider->requests[1]->amount->getAmount());
    }

    public function test_not_payable_and_provider_failure_show_a_page(): void {
        $draft = $this->invoice(['status' => Invoice::STATUS_DRAFT, 'number' => 'E-1']);
        $this->assertNull(app(InvoicePaymentLinkService::class)->urlFor($draft));

        $invoice = $this->invoice();
        $token = $this->token($invoice);
        $this->provider->failCheckout = true;
        $this->get(route('payments.show', $token))->assertStatus(503)->assertSee(__('payments.page.unavailable'));
        $this->assertSame(OnlinePaymentStatus::Failed, OnlinePayment::query()->firstOrFail()->status);

        $invoice->forceFill(['status' => Invoice::STATUS_CANCELLED])->save();
        $this->get(route('payments.show', $token))->assertOk()->assertSee(__('payments.page.not_payable'));

        $this->get(route('payments.show', str_repeat('a', 40)))->assertNotFound();
    }

    public function test_daily_refresh_catches_lost_webhooks_and_refunds(): void {
        $invoice = $this->invoice();
        $this->get(route('payments.show', $this->token($invoice)));
        $this->provider->settle('pay_1');

        $this->artisan('invoicing:online-payments-refresh')->assertSuccessful();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);

        $this->provider->refund('pay_1', $this->eur('119.00'));
        $this->artisan('invoicing:online-payments-refresh')->assertSuccessful();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->refresh()->status);
        $this->assertSame('119.00', OnlinePayment::query()->firstOrFail()->refunded_amount->getAmount());
    }

    public function test_without_active_provider_there_is_no_link(): void {
        $this->registerPlugins();
        $this->assertNull(app(InvoicePaymentLinkService::class)->urlFor($this->invoice()));
    }
}
