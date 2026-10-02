<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SumUpCheckoutClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SumUp\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Plugins\SumUp\SumUpPlugin;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Support\UrlSafety;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;

/** SumUp Checkouts API mit gehostetem Checkout. */
class SumUpCheckoutClient {
    /** Ein gehosteter Checkout gilt 30 Minuten. */
    private const SESSION_MINUTES = 30;

    private ?PluginApiClient $api = null;

    /** @param array{api_key: string, merchant_code: string, api_base: string} $config */
    public function __construct(private readonly array $config) {}

    public function createCheckout(OnlinePaymentRequest $request): OnlinePaymentCheckout {
        $payload = array_filter([
            'checkout_reference' => mb_substr($request->reference, 0, 64),
            // SumUp erwartet den Betrag als Zahl in Haupteinheiten.
            'amount' => (float) $request->amount->withScale(2)->getAmount(),
            'currency' => $request->amount->getCurrency()->value,
            'merchant_code' => $this->config['merchant_code'],
            'description' => mb_substr($request->description, 0, 255),
            'redirect_url' => $request->returnUrl,
            // Rückmeldeadresse für Statuswechsel — nur wenn SumUp sie erreichen kann.
            'return_url' => UrlSafety::isPubliclyRoutableHttpUrl($request->webhookUrl) ? $request->webhookUrl : null,
            'hosted_checkout' => ['enabled' => true],
        ], static fn ($value): bool => $value !== null);

        $response = $this->api()->postJson($this->config['api_base'] . '/v0.1/checkouts', $payload);
        $body = (array) ($response->json() ?? []);
        if (! $response->successful() || ! is_string($body['id'] ?? null) || ! is_string($body['hosted_checkout_url'] ?? null)) {
            throw new RuntimeException('SumUp: Checkout nicht angelegt (HTTP ' . $response->status() . ').');
        }

        return new OnlinePaymentCheckout($body['id'], $body['hosted_checkout_url'], CarbonImmutable::now()->addMinutes(self::SESSION_MINUTES));
    }

    public function fetchCheckout(string $checkoutId): OnlinePaymentSnapshot {
        $response = $this->api()->getResponse($this->config['api_base'] . '/v0.1/checkouts/' . rawurlencode($checkoutId));
        $checkout = (array) ($response->json() ?? []);
        if (! $response->successful() || ($checkout['id'] ?? null) !== $checkoutId) {
            throw new RuntimeException('SumUp: Checkout nicht lesbar (HTTP ' . $response->status() . ').');
        }

        $currency = CurrencyCode::from((string) ($checkout['currency'] ?? ''));
        $amount = Money::ofFloat((float) ($checkout['amount'] ?? 0), $currency, 2);
        $transactions = array_values(array_filter((array) ($checkout['transactions'] ?? []), 'is_array'));
        $settled = array_values(array_filter($transactions, static fn (array $t): bool => in_array($t['status'] ?? null, ['SUCCESSFUL', 'REFUNDED'], true)));
        $refunded = $settled !== [] && array_filter($settled, static fn (array $t): bool => $t['status'] === 'REFUNDED') !== [];

        $status = match ((string) ($checkout['status'] ?? '')) {
            'PAID' => $refunded ? OnlinePaymentStatus::Refunded : OnlinePaymentStatus::Paid,
            'FAILED' => OnlinePaymentStatus::Failed,
            'EXPIRED' => OnlinePaymentStatus::Expired,
            default => OnlinePaymentStatus::Open,
        };
        $first = $settled[0] ?? [];

        return new OnlinePaymentSnapshot(
            status: $status,
            amount: $amount,
            paidAt: is_string($first['timestamp'] ?? null) ? CarbonImmutable::parse($first['timestamp']) : null,
            // Gebühren meldet SumUp nicht je Checkout.
            fee: null,
            refunded: $refunded ? $amount : null,
            method: is_string($first['payment_type'] ?? null) ? mb_strtolower(mb_substr($first['payment_type'], 0, 40)) : null,
        );
    }

    public function checkCredentials(): bool {
        return $this->api()->getResponse($this->config['api_base'] . '/v0.1/me')->successful();
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(SumUpPlugin::ID, $this->config['api_base']);
            $this->api->setAuthentication(new BearerAuthentication($this->config['api_key']));
        }

        return $this->api;
    }
}
