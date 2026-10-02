<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MolliePaymentClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Mollie\Api;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Enums\Invoicing\OnlinePaymentStatus;
use App\Plugins\Mollie\MolliePlugin;
use App\Plugins\Support\Payments\{OnlinePaymentCheckout, OnlinePaymentRequest, OnlinePaymentSnapshot};
use App\Plugins\Support\{PluginApiClient, PluginHttpFactory};
use App\Support\UrlSafety;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use RuntimeException;

/** Mollie Payments API v2. */
class MolliePaymentClient {
    private const LOCALES = ['de' => 'de_DE', 'en' => 'en_US', 'es' => 'es_ES', 'fr' => 'fr_FR', 'it' => 'it_IT'];

    private ?PluginApiClient $api = null;

    /** @param array{api_key: string, api_base: string} $config */
    public function __construct(private readonly array $config) {}

    public function createPayment(OnlinePaymentRequest $request): OnlinePaymentCheckout {
        $payload = array_filter([
            'amount' => self::amount($request->amount),
            'description' => mb_substr($request->description, 0, 255),
            'redirectUrl' => $request->returnUrl,
            // Mollie lehnt nicht erreichbare Adressen ab (lokale Installation) — dann trägt die Rückkehr den Abgleich.
            'webhookUrl' => UrlSafety::isPubliclyRoutableHttpUrl($request->webhookUrl) ? $request->webhookUrl : null,
            'metadata' => ['reference' => $request->reference],
            'locale' => self::LOCALES[substr($request->locale, 0, 2)] ?? null,
        ], static fn ($value): bool => $value !== null);

        $response = $this->api()->postJson($this->config['api_base'] . '/v2/payments', $payload, [
            'headers' => ['Idempotency-Key' => 'wd-' . $request->reference],
        ]);
        $body = (array) ($response->json() ?? []);
        $checkout = data_get($body, '_links.checkout.href');
        if (! $response->successful() || ! is_string($body['id'] ?? null) || ! is_string($checkout)) {
            throw new RuntimeException('Mollie: Zahlung nicht angelegt (HTTP ' . $response->status() . ').');
        }

        return new OnlinePaymentCheckout(
            $body['id'],
            $checkout,
            is_string($body['expiresAt'] ?? null) ? CarbonImmutable::parse($body['expiresAt']) : null,
        );
    }

    public function fetchPayment(string $paymentId): OnlinePaymentSnapshot {
        $response = $this->api()->getResponse($this->config['api_base'] . '/v2/payments/' . rawurlencode($paymentId));
        $payment = (array) ($response->json() ?? []);
        if (! $response->successful() || ($payment['id'] ?? null) !== $paymentId) {
            throw new RuntimeException('Mollie: Zahlung nicht lesbar (HTTP ' . $response->status() . ').');
        }

        $amount = self::money((array) ($payment['amount'] ?? []));
        $refunded = self::money((array) ($payment['amountRefunded'] ?? []), $amount->getCurrency());
        $chargedBack = self::money((array) ($payment['amountChargedBack'] ?? []), $amount->getCurrency());
        $reversed = $refunded->plus($chargedBack);

        $status = match ((string) ($payment['status'] ?? '')) {
            'paid' => $amount->isPositive() && $reversed->compareTo($amount) >= 0 ? OnlinePaymentStatus::Refunded : OnlinePaymentStatus::Paid,
            'canceled' => OnlinePaymentStatus::Canceled,
            'expired' => OnlinePaymentStatus::Expired,
            'failed' => OnlinePaymentStatus::Failed,
            default => OnlinePaymentStatus::Open,
        };

        return new OnlinePaymentSnapshot(
            status: $status,
            amount: $amount,
            paidAt: is_string($payment['paidAt'] ?? null) ? CarbonImmutable::parse($payment['paidAt']) : null,
            // Mollie stellt Gebühren gesammelt in Rechnung, nicht je Zahlung.
            fee: null,
            refunded: $reversed,
            method: is_string($payment['method'] ?? null) ? mb_substr($payment['method'], 0, 40) : null,
        );
    }

    public function checkCredentials(): bool {
        return $this->api()->getResponse($this->config['api_base'] . '/v2/methods')->successful();
    }

    /** @return array{currency: string, value: string} */
    private static function amount(Money $money): array {
        return ['currency' => $money->getCurrency()->value, 'value' => $money->withScale(2)->getAmount()];
    }

    /** @param array<string, mixed> $value */
    private static function money(array $value, ?CurrencyCode $fallback = null): Money {
        $currency = CurrencyCode::tryFrom((string) ($value['currency'] ?? '')) ?? $fallback ?? throw new RuntimeException('Mollie: Währung fehlt.');

        return Money::of((string) ($value['value'] ?? '0'), $currency);
    }

    private function api(): PluginApiClient {
        if ($this->api === null) {
            $this->api = app(PluginHttpFactory::class)->client(MolliePlugin::ID, $this->config['api_base']);
            $this->api->setAuthentication(new BearerAuthentication($this->config['api_key']));
        }

        return $this->api;
    }
}
