<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceOrderImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Services;

use App\Enums\Integration\ShopOrderStatus;
use App\Models\Platform\Organization;
use App\Plugins\Support\ShopOrders\{AbstractShopOrderImportService, RemoteOrder};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Plugins\WooCommerce\Api\WooCommerceClientFactory;
use App\Plugins\WooCommerce\Models\{WooCommerceConnection, WooCommerceOrder};
use App\Plugins\WooCommerce\WooCommercePlugin;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{PersonNameHelper, StringHelper};
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * WooCommerce-Adapter des Shop-Imports (MVP-1325): Bestellungen über
 * `modified_after` in UTC. Der Status kommt aus dem festen Schlüssel
 * (`processing`, `completed` …), nie aus der Anzeige; fremde Status von
 * Erweiterungen gelten als in Bearbeitung und bleiben als Rohwert sichtbar.
 * Gastbestellungen tragen die Käuferkennung `guest:<e-mail>`.
 *
 * @extends AbstractShopOrderImportService<WooCommerceOrder, WooCommerceConnection>
 */
class WooCommerceOrderImportService extends AbstractShopOrderImportService {
    private const STATUSES = [
        'pending' => ShopOrderStatus::Open,
        'on-hold' => ShopOrderStatus::Open,
        'processing' => ShopOrderStatus::Processing,
        'completed' => ShopOrderStatus::Completed,
        'cancelled' => ShopOrderStatus::Cancelled,
        'failed' => ShopOrderStatus::Cancelled,
        'refunded' => ShopOrderStatus::Refunded,
    ];

    public function pluginId(): string {
        return WooCommercePlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return app(WooCommerceClientFactory::class)->isConfigured($organizationId);
    }

    public function orderUrl(Model $order): ?string {
        $baseUrl = app(WooCommerceClientFactory::class)->baseUrl((int) $order->getAttribute('organization_id'));
        $id = (string) $order->getAttribute('remote_order_id');

        return $baseUrl !== null && ctype_digit($id) ? $baseUrl . '/wp-admin/post.php?post=' . $id . '&action=edit' : null;
    }

    protected function orderModel(): string {
        return WooCommerceOrder::class;
    }

    protected function connectionModel(): string {
        return WooCommerceConnection::class;
    }

    protected function fetch(Organization $organization, CarbonImmutable $since): iterable {
        return app(WooCommerceClientFactory::class)->for((int) $organization->id)->orders($since);
    }

    protected function existingRemoteIds(Organization $organization, array $ids): array {
        return app(WooCommerceClientFactory::class)->for((int) $organization->id)->existingOrderIds($ids);
    }

    protected function normalize(array $raw, Organization $organization): RemoteOrder {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_order');
        }
        $status = is_string($raw['status'] ?? null) ? $raw['status'] : '';
        $billing = is_array($raw['billing'] ?? null) ? $raw['billing'] : [];
        $email = StringHelper::trimToNull($billing['email'] ?? null);
        $customerId = is_numeric($raw['customer_id'] ?? null) ? (int) $raw['customer_id'] : 0;
        $currency = CurrencyCode::tryFrom(strtoupper((string) ($raw['currency'] ?? '')));
        $gross = RemoteOrder::amount($raw['total'] ?? null);
        $tax = RemoteOrder::amount($raw['total_tax'] ?? null);

        return new RemoteOrder(
            id: $id,
            number: StringHelper::trimToNull($raw['number'] ?? null),
            status: self::STATUSES[$status] ?? ShopOrderStatus::Processing,
            remoteStatus: $status !== '' ? $status : null,
            currency: $currency,
            grossAmount: $gross,
            netAmount: $gross !== null && $tax !== null ? Money::of($gross, $currency ?? CurrencyCode::Euro)->minus(Money::of($tax, $currency ?? CurrencyCode::Euro))->getAmount() : null,
            orderedAt: self::utc($raw['date_created_gmt'] ?? null),
            modifiedAt: self::utc($raw['date_modified_gmt'] ?? null),
            buyerKey: $customerId > 0 ? 'customer:' . $customerId : ($email !== null ? 'guest:' . mb_strtolower($email) : null),
            buyerName: StringHelper::trimToNull(PersonNameHelper::join(['first_name' => $billing['first_name'] ?? null, 'last_name' => $billing['last_name'] ?? null])),
            buyerEmail: $email,
            buyerCompany: StringHelper::trimToNull($billing['company'] ?? null),
            items: self::items($raw['line_items'] ?? null),
            refundedAmount: self::refunded($raw['refunds'] ?? null, $currency ?? CurrencyCode::Euro),
        );
    }

    /** Summe der Erstattungen; WooCommerce nennt sie als negative Beträge. */
    private static function refunded(mixed $refunds, CurrencyCode $currency): ?string {
        if (! is_array($refunds)) {
            return null;
        }
        $sum = Money::zero($currency);
        foreach ($refunds as $refund) {
            $amount = is_array($refund) ? RemoteOrder::amount($refund['total'] ?? null) : null;
            if ($amount === null) {
                return null;
            }
            $sum = $sum->plus(Money::of($amount, $currency)->abs());
        }

        return $sum->getAmount();
    }

    private static function utc(mixed $value): ?CarbonImmutable {
        return is_string($value) && trim($value) !== '' ? CarbonImmutable::parse($value, 'UTC') : null;
    }

    /** @return list<array{name: string, sku: ?string, quantity: string, total: ?string}> */
    private static function items(mixed $lines): array {
        $items = [];
        foreach (is_array($lines) ? $lines : [] as $line) {
            if (! is_array($line) || ! is_string($line['name'] ?? null)) {
                continue;
            }
            $items[] = [
                'name' => mb_substr($line['name'], 0, 255),
                'sku' => StringHelper::trimToNull($line['sku'] ?? null),
                'quantity' => is_numeric($line['quantity'] ?? null) ? (string) $line['quantity'] : '0',
                'total' => RemoteOrder::amount($line['total'] ?? null),
            ];
        }

        return $items;
    }
}
