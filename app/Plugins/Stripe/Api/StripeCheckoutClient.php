<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StripeCheckoutClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Stripe\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Plugins\Stripe\StripePlugin;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;

/** Stripe Checkout Sessions (REST, formularkodiert). */
class StripeCheckoutClient {
    private const LOCALES = ['de', 'en', 'es', 'fr', 'it'];

    /** Stripe erlaubt 30 Minuten bis 24 Stunden. */
    private const SESSION_MINUTES = 60;

    private ?PluginApiClient $api = null;

    /** @param array{secret_key: string, api_base: string} $config */
    public function __construct(private readonly array $config) {}

    public function createSession(OnlinePaymentRequest $request): OnlinePaymentCheckout {
        $locale = substr($request->locale, 0, 2);
        $form = array_filter([
            'mode' => 'payment',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($request->amount->getCurrency()->value),
            'line_items[0][price_data][unit_amount]' => $request->amount->getMinorAmount(),
            'line_items[0][price_data][product_data][name]' => $request->description,
            'success_url' => $request->returnUrl,
            'client_reference_id' => $request->reference,
            'metadata[reference]' => $request->reference,
            'payment_intent_data[metadata][reference]' => $request->reference,
            'locale' => in_array($locale, self::LOCALES, true) ? $locale : 'auto',
            'expires_at' => CarbonImmutable::now()->addMinutes(self::SESSION_MINUTES)->getTimestamp(),
            'customer_email' => $request->customerEmail,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $response = $this->api()->requestResponse('post', $this->config['api_base'] . '/v1/checkout/sessions', [
            'form_params' => $form,
            // Ein wiederholter Versand legt keine zweite Session an.
            'headers' => ['Idempotency-Key' => 'wd-' . $request->reference],
        ]);
        $body = (array) ($response->json() ?? []);
        if (! $response->successful() || ! is_string($body['id'] ?? null) || ! is_string($body['url'] ?? null)) {
            throw new RuntimeException('Stripe: Checkout Session nicht angelegt (HTTP ' . $response->status() . ').');
        }

        return new OnlinePaymentCheckout(
            $body['id'],
            $body['url'],
            isset($body['expires_at']) ? CarbonImmutable::createFromTimestamp((int) $body['expires_at']) : null,
        );
    }

    public function fetchSession(string $sessionId): OnlinePaymentSnapshot {
        $response = $this->api()->getResponse($this->config['api_base'] . '/v1/checkout/sessions/' . rawurlencode($sessionId), [
            'expand' => ['payment_intent.latest_charge.balance_transaction'],
        ]);
        $session = (array) ($response->json() ?? []);
        if (! $response->successful() || ($session['id'] ?? null) !== $sessionId) {
            throw new RuntimeException('Stripe: Checkout Session nicht lesbar (HTTP ' . $response->status() . ').');
        }

        $currency = CurrencyCode::from(strtoupper((string) ($session['currency'] ?? '')));
        $amount = Money::ofMinor((int) ($session['amount_total'] ?? 0), $currency);
        $charge = (array) data_get($session, 'payment_intent.latest_charge', []);
        $refunded = Money::ofMinor((int) ($charge['amount_refunded'] ?? 0), $currency);

        $status = match (true) {
            ($session['payment_status'] ?? null) === 'paid' && $refunded->compareTo($amount) >= 0 && $amount->isPositive() => OnlinePaymentStatus::Refunded,
            ($session['payment_status'] ?? null) === 'paid' => OnlinePaymentStatus::Paid,
            ($session['status'] ?? null) === 'expired' => OnlinePaymentStatus::Expired,
            data_get($session, 'payment_intent.status') === 'canceled' => OnlinePaymentStatus::Canceled,
            default => OnlinePaymentStatus::Open,
        };

        // Die Gebühr steht in der Saldowährung des Kontos — nur übernehmen, wenn sie zur Zahlung passt.
        $balance = (array) ($charge['balance_transaction'] ?? []);
        $fee = isset($balance['fee']) && strtoupper((string) ($balance['currency'] ?? '')) === $currency->value
            ? Money::ofMinor((int) $balance['fee'], $currency)
            : null;

        return new OnlinePaymentSnapshot(
            status: $status,
            amount: $amount,
            paidAt: isset($charge['created']) ? CarbonImmutable::createFromTimestamp((int) $charge['created']) : null,
            fee: $fee,
            refunded: $refunded,
            method: is_string(data_get($charge, 'payment_method_details.type')) ? mb_substr((string) data_get($charge, 'payment_method_details.type'), 0, 40) : null,
        );
    }

    public function checkCredentials(): bool {
        return $this->api()->getResponse($this->config['api_base'] . '/v1/balance')->successful();
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(StripePlugin::ID, $this->config['api_base']);
            $this->api->setAuthentication(new BearerAuthentication($this->config['secret_key']));
        }

        return $this->api;
    }
}
