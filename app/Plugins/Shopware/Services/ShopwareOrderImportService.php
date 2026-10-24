<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareOrderImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware\Services;

use App\Enums\Integration\ShopOrderStatus;
use App\Models\Platform\Organization;
use App\Plugins\Shopware\Api\ShopwareClientFactory;
use App\Plugins\Shopware\Models\{ShopwareConnection, ShopwareOrder};
use App\Plugins\Shopware\ShopwarePlugin;
use App\Plugins\Support\ShopOrders\{AbstractShopOrderImportService, RemoteOrder};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{PersonNameHelper, StringHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Shopware-6-Adapter des Shop-Imports (MVP-1326): Bestellungen über die
 * Admin-Suche. Der Status kommt aus dem technischen Namen des Bestellstatus
 * (`open`, `in_progress`, `completed`, `cancelled`), nie aus der Anzeige.
 * „Erstattet“ kennt nur der Zahlungsstatus: Steht die jüngste Transaktion auf
 * `refunded`, gilt die Bestellung als erstattet. Den Betrag liefern die
 * abgeschlossenen Erstattungen an den Captures der Transaktionen.
 * Kundenkonten tragen die Käuferkennung `customer:<id>`; Gastkonten legt
 * Shopware je Bestellung neu an, sie zählen deshalb über `guest:<e-mail>`.
 *
 * @extends AbstractShopOrderImportService<ShopwareOrder, ShopwareConnection>
 */
class ShopwareOrderImportService extends AbstractShopOrderImportService {
    private const STATUSES = [
        'open' => ShopOrderStatus::Open,
        'in_progress' => ShopOrderStatus::Processing,
        'completed' => ShopOrderStatus::Completed,
        'cancelled' => ShopOrderStatus::Cancelled,
    ];

    public function pluginId(): string {
        return ShopwarePlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return app(ShopwareClientFactory::class)->isConfigured($organizationId);
    }

    public function orderUrl(Model $order): ?string {
        $baseUrl = app(ShopwareClientFactory::class)->baseUrl((int) $order->getAttribute('organization_id'));
        $id = (string) $order->getAttribute('remote_order_id');

        return $baseUrl !== null && ctype_xdigit($id) ? $baseUrl . '/admin#/sw/order/detail/' . $id : null;
    }

    protected function orderModel(): string {
        return ShopwareOrder::class;
    }

    protected function connectionModel(): string {
        return ShopwareConnection::class;
    }

    protected function fetch(Organization $organization, CarbonImmutable $since): iterable {
        return app(ShopwareClientFactory::class)->for((int) $organization->id)->orders($since);
    }

    protected function existingRemoteIds(Organization $organization, array $ids): array {
        return app(ShopwareClientFactory::class)->for((int) $organization->id)->existingOrderIds($ids);
    }

    protected function normalize(array $raw, Organization $organization): RemoteOrder {
        $id = is_string($raw['id'] ?? null) ? $raw['id'] : '';
        if (preg_match('/^[0-9a-f]{32}$/', $id) !== 1) {
            throw new IncompleteRemoteReadException('invalid_order');
        }
        $state = data_get($raw, 'stateMachineState.technicalName');
        $state = is_string($state) ? $state : '';
        $transactions = is_array($raw['transactions'] ?? null) ? $raw['transactions'] : [];
        $payment = $transactions !== [] ? data_get(end($transactions), 'stateMachineState.technicalName') : null;
        $refunded = $payment === 'refunded';
        $currency = CurrencyCode::tryFrom(strtoupper((string) data_get($raw, 'currency.isoCode', '')));
        $gross = RemoteOrder::amount($raw['amountTotal'] ?? null);
        $buyer = is_array($raw['orderCustomer'] ?? null) ? $raw['orderCustomer'] : [];
        $email = StringHelper::trimToNull($buyer['email'] ?? null);
        $customerId = StringHelper::trimToNull($buyer['customerId'] ?? null);
        $guest = data_get($buyer, 'customer.guest') === true;

        return new RemoteOrder(
            id: $id,
            number: StringHelper::trimToNull($raw['orderNumber'] ?? null),
            status: $refunded ? ShopOrderStatus::Refunded : self::STATUSES[$state] ?? ShopOrderStatus::Processing,
            remoteStatus: $state !== '' ? $state : null,
            currency: $currency,
            grossAmount: $gross,
            netAmount: RemoteOrder::amount($raw['amountNet'] ?? null),
            orderedAt: self::date($raw['orderDateTime'] ?? null),
            modifiedAt: self::date($raw['updatedAt'] ?? null) ?? self::date($raw['createdAt'] ?? null),
            buyerKey: $customerId !== null && ! $guest ? 'customer:' . $customerId : ($email !== null ? 'guest:' . mb_strtolower($email) : null),
            buyerName: StringHelper::trimToNull(PersonNameHelper::join(['first_name' => $buyer['firstName'] ?? null, 'last_name' => $buyer['lastName'] ?? null])),
            buyerEmail: $email,
            buyerCompany: StringHelper::trimToNull($buyer['company'] ?? null),
            items: self::items($raw['lineItems'] ?? null),
            refundedAmount: self::refundedAmount($transactions, is_string($payment) ? $payment : '', $gross, $currency ?? CurrencyCode::Euro),
        );
    }

    /**
     * Summe der abgeschlossenen Erstattungen. Ohne Erstattungsdatensatz steht
     * ein von Hand gesetztes „erstattet“ für den vollen Betrag; „teilweise
     * erstattet“ ohne Datensatz bleibt unbekannt.
     *
     * @param  array<mixed>  $transactions
     */
    private static function refundedAmount(array $transactions, string $payment, ?string $gross, CurrencyCode $currency): ?string {
        $refunds = [];
        foreach ($transactions as $transaction) {
            foreach ((array) data_get($transaction, 'captures', []) as $capture) {
                foreach ((array) data_get($capture, 'refunds', []) as $refund) {
                    $amount = RemoteOrder::amount(data_get($refund, 'amount.totalPrice'));
                    if (data_get($refund, 'stateMachineState.technicalName') === 'completed' && $amount !== null) {
                        $refunds[] = Money::of($amount, $currency);
                    }
                }
            }
        }

        return match (true) {
            $refunds !== [] => Money::sum($refunds, $currency)->getAmount(),
            $payment === 'refunded' => $gross,
            $payment === 'refunded_partially' => null,
            default => Money::zero($currency)->getAmount(),
        };
    }

    private static function date(mixed $value): ?CarbonImmutable {
        return is_string($value) && trim($value) !== '' ? CarbonImmutable::parse($value)->utc() : null;
    }

    /** @return list<array{name: string, sku: ?string, quantity: string, total: ?string}> */
    private static function items(mixed $lines): array {
        $items = [];
        foreach (is_array($lines) ? $lines : [] as $line) {
            if (! is_array($line) || ! is_string($line['label'] ?? null)) {
                continue;
            }
            $items[] = [
                'name' => mb_substr($line['label'], 0, 255),
                'sku' => StringHelper::trimToNull(data_get($line, 'payload.productNumber')),
                'quantity' => is_numeric($line['quantity'] ?? null) ? (string) $line['quantity'] : '0',
                'total' => RemoteOrder::amount($line['totalPrice'] ?? null),
            ];
        }

        return $items;
    }
}
