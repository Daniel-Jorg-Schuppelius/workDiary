<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentPluginsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Models\Platform\Organization;
use App\Plugins\Mollie\MolliePlugin;
use App\Plugins\Stripe\StripePlugin;
use App\Plugins\SumUp\SumUpPlugin;
use App\Plugins\Support\Payments\OnlinePaymentRequest;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Psr\Http\Message\RequestInterface;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/** MVP-1068–1070: Stripe, Mollie und SumUp gegen ihre Schnittstellen (Anfrage und Auswertung). */
final class OnlinePaymentPluginsTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
    }

    private function request(): OnlinePaymentRequest {
        return new OnlinePaymentRequest(
            reference: 'abc123',
            amount: Money::of('119.00', CurrencyCode::Euro),
            description: 'Rechnung R-1',
            returnUrl: 'https://app.example.com/zahlen/x/fertig',
            webhookUrl: 'http://localhost/webhooks/online-payment/x',
            locale: 'de',
            customerEmail: 'kunde@example.com',
        );
    }

    public function test_stripe_creates_session_and_reads_fee_and_refund(): void {
        $this->enablePluginFor($this->org, StripePlugin::ID, ['secret_key' => 'sk_test_123']);
        $http = FakePluginHttp::fake([
            'https://api.stripe.com/v1/checkout/sessions' => ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'expires_at' => 1_900_000_000],
            'https://api.stripe.com/v1/checkout/sessions/cs_test_1*' => [
                'id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 11900, 'currency' => 'eur',
                'payment_intent' => ['status' => 'succeeded', 'latest_charge' => [
                    'created' => 1_800_000_000, 'amount_refunded' => 1900,
                    'balance_transaction' => ['fee' => 205, 'currency' => 'eur'],
                    'payment_method_details' => ['type' => 'card'],
                ]],
            ],
        ]);
        $plugin = new StripePlugin;

        $checkout = $plugin->createCheckout($this->org, $this->request());
        $this->assertSame('cs_test_1', $checkout->providerReference);
        $http->assertSent(static function (RequestInterface $request): bool {
            parse_str((string) $request->getBody(), $form);

            return $request->getMethod() === 'POST'
                && $request->getHeaderLine('Idempotency-Key') === 'wd-abc123'
                && $request->getHeaderLine('Authorization') === 'Bearer sk_test_123'
                && data_get($form, 'line_items.0.price_data.unit_amount') === '11900'
                && data_get($form, 'line_items.0.price_data.currency') === 'eur'
                && ($form['client_reference_id'] ?? null) === 'abc123'
                && ($form['locale'] ?? null) === 'de';
        });

        $snapshot = $plugin->fetchPayment($this->org, 'cs_test_1');
        $this->assertSame(OnlinePaymentStatus::Paid, $snapshot->status);
        $this->assertSame('119.00', $snapshot->amount->getAmount());
        $this->assertSame('2.05', $snapshot->fee?->getAmount());
        $this->assertSame('19.00', $snapshot->refunded?->getAmount());
        $this->assertSame('card', $snapshot->method);

        $webhook = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['data' => ['object' => ['object' => 'checkout.session', 'id' => 'cs_test_1']]]));
        $this->assertSame('cs_test_1', $plugin->webhookReference($webhook));
        $charge = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['data' => ['object' => ['object' => 'charge', 'id' => 'ch_1']]]));
        $this->assertNull($plugin->webhookReference($charge));
    }

    public function test_mollie_creates_payment_without_unreachable_webhook_and_reads_refunds(): void {
        $this->enablePluginFor($this->org, MolliePlugin::ID, ['api_key' => 'test_abc']);
        $http = FakePluginHttp::fake([
            'https://api.mollie.com/v2/payments' => ['id' => 'tr_abc', 'status' => 'open', 'expiresAt' => '2026-10-02T13:00:00+00:00', '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/tr_abc']]],
            'https://api.mollie.com/v2/payments/tr_abc' => [
                'id' => 'tr_abc', 'status' => 'paid', 'amount' => ['currency' => 'EUR', 'value' => '119.00'],
                'amountRefunded' => ['currency' => 'EUR', 'value' => '119.00'], 'paidAt' => '2026-10-02T12:10:00+00:00', 'method' => 'paypal',
            ],
        ]);
        $plugin = new MolliePlugin;

        $checkout = $plugin->createCheckout($this->org, $this->request());
        $this->assertSame('https://www.mollie.com/checkout/tr_abc', $checkout->checkoutUrl);
        $http->assertSent(static function (RequestInterface $request): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return data_get($body, 'amount.value') === '119.00'
                && data_get($body, 'amount.currency') === 'EUR'
                && ! array_key_exists('webhookUrl', $body)
                && ($body['locale'] ?? null) === 'de_DE'
                && $request->getHeaderLine('Idempotency-Key') === 'wd-abc123';
        });

        $snapshot = $plugin->fetchPayment($this->org, 'tr_abc');
        $this->assertSame(OnlinePaymentStatus::Refunded, $snapshot->status);
        $this->assertNull($snapshot->fee);
        $this->assertSame('tr_abc', $plugin->webhookReference(Request::create('/', 'POST', ['id' => 'tr_abc'])));
        $this->assertNull($plugin->webhookReference(Request::create('/', 'POST', ['id' => '../x'])));
    }

    public function test_sumup_creates_hosted_checkout_and_reads_status(): void {
        $this->enablePluginFor($this->org, SumUpPlugin::ID, ['api_key' => 'sup_sk_x', 'merchant_code' => 'MC123']);
        $http = FakePluginHttp::fake([
            'https://api.sumup.com/v0.1/checkouts' => ['id' => '4e425463-3e1b-431d-83fa-1e51c2925e99', 'status' => 'PENDING', 'hosted_checkout_url' => 'https://checkout.sumup.com/pay/x'],
            'https://api.sumup.com/v0.1/checkouts/4e425463-3e1b-431d-83fa-1e51c2925e99' => [
                'id' => '4e425463-3e1b-431d-83fa-1e51c2925e99', 'status' => 'PAID', 'amount' => 119.0, 'currency' => 'EUR',
                'transactions' => [['status' => 'SUCCESSFUL', 'timestamp' => '2026-10-02T12:00:00Z', 'payment_type' => 'ECOM', 'amount' => 119.0]],
            ],
        ]);
        $plugin = new SumUpPlugin;

        $checkout = $plugin->createCheckout($this->org, $this->request());
        $this->assertSame('https://checkout.sumup.com/pay/x', $checkout->checkoutUrl);
        $http->assertSent(static function (RequestInterface $request): bool {
            $body = (array) json_decode((string) $request->getBody(), true);

            return ($body['merchant_code'] ?? null) === 'MC123'
                && (float) ($body['amount'] ?? 0) === 119.0
                && data_get($body, 'hosted_checkout.enabled') === true
                && ! array_key_exists('return_url', $body)
                && ($body['redirect_url'] ?? null) === 'https://app.example.com/zahlen/x/fertig';
        });

        $snapshot = $plugin->fetchPayment($this->org, '4e425463-3e1b-431d-83fa-1e51c2925e99');
        $this->assertSame(OnlinePaymentStatus::Paid, $snapshot->status);
        $this->assertSame('119.00', $snapshot->amount->getAmount());
        $this->assertSame('ecom', $snapshot->method);

        $webhook = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['event_type' => 'CHECKOUT_STATUS_CHANGED', 'id' => '4e425463-3e1b-431d-83fa-1e51c2925e99']));
        $this->assertSame('4e425463-3e1b-431d-83fa-1e51c2925e99', $plugin->webhookReference($webhook));
    }
}
