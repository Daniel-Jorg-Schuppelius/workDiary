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
use Illuminate\Database\Eloquent\Model;

/**
 * Shopware-6-Adapter des Shop-Imports (MVP-1326): Bestellungen über die
 * Admin-Suche. Der Status kommt aus dem technischen Namen des Bestellstatus
 * (`open`, `in_progress`, `completed`, `cancelled`), nie aus der Anzeige.
 * „Erstattet“ kennt nur der Zahlungsstatus: Steht die jüngste Transaktion auf
 * `refunded`, gilt die Bestellung als erstattet.
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

    protected function normalize(array $raw, Organization $organization): RemoteOrder {
        $id = is_string($raw['id'] ?? null) ? $raw['id'] : '';
        if (preg_match('/^[0-9a-f]{32}$/', $id) !== 1) {
            throw new IncompleteRemoteReadException('invalid_order');
        }
        $state = data_get($raw, 'stateMachineState.technicalName');
        $state = is_string($state) ? $state : '';
        $transactions = is_array($raw['transactions'] ?? null) ? $raw['transactions'] : [];
        $refunded = $transactions !== [] && data_get(end($transactions), 'stateMachineState.technicalName') === 'refunded';
        $buyer = is_array($raw['orderCustomer'] ?? null) ? $raw['orderCustomer'] : [];
        $email = StringHelper::trimToNull($buyer['email'] ?? null);
        $customerId = StringHelper::trimToNull($buyer['customerId'] ?? null);
        $guest = data_get($buyer, 'customer.guest') === true;

        return new RemoteOrder(
            id: $id,
            number: StringHelper::trimToNull($raw['orderNumber'] ?? null),
            status: $refunded ? ShopOrderStatus::Refunded : self::STATUSES[$state] ?? ShopOrderStatus::Processing,
            remoteStatus: $state !== '' ? $state : null,
            currency: CurrencyCode::tryFrom(strtoupper((string) data_get($raw, 'currency.isoCode', ''))),
            grossAmount: RemoteOrder::amount($raw['amountTotal'] ?? null),
            netAmount: RemoteOrder::amount($raw['amountNet'] ?? null),
            orderedAt: self::date($raw['orderDateTime'] ?? null),
            modifiedAt: self::date($raw['updatedAt'] ?? null) ?? self::date($raw['createdAt'] ?? null),
            buyerKey: $customerId !== null && ! $guest ? 'customer:' . $customerId : ($email !== null ? 'guest:' . mb_strtolower($email) : null),
            buyerName: StringHelper::trimToNull(PersonNameHelper::join(['first_name' => $buyer['firstName'] ?? null, 'last_name' => $buyer['lastName'] ?? null])),
            buyerEmail: $email,
            buyerCompany: StringHelper::trimToNull($buyer['company'] ?? null),
            items: self::items($raw['lineItems'] ?? null),
        );
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
