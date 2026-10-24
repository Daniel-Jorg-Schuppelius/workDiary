<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopOrderImportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Services;

use App\Enums\Integration\ShopOrderStatus;
use App\Models\Platform\Organization;
use App\Plugins\PrestaShop\Api\PrestaShopClientFactory;
use App\Plugins\PrestaShop\Models\{PrestaShopConnection, PrestaShopOrder};
use App\Plugins\PrestaShop\PrestaShopPlugin;
use App\Plugins\Support\ShopOrders\{AbstractShopOrderImportService, RemoteOrder};
use App\Plugins\Support\TaskSync\IncompleteRemoteReadException;
use App\Support\Tz;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\{PersonNameHelper, StringHelper};
use CommonToolkit\ValueObjects\Money;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;

/**
 * PrestaShop-Adapter des Shop-Imports (MVP-1327): Bestellungen über
 * `date_upd` in Shop-Zeit (Einstellung, sonst die beim Prüfen gelesene
 * `PS_TIMEZONE`, sonst die Zeitzone der Organisation), Kunden und Gutschriften
 * je Seite gesammelt. Der Status kommt aus den festen Merkmalen des
 * Bestellstatus — Vorlage `order_canceled`/`refund`/`payment_error`, sonst
 * versendet, bezahlt — nie aus dem frei benannten Statusnamen.
 * Einen Link in die Verwaltung gibt es nicht: ihr Ordner ist je Shop geheim und
 * ihre Links tragen ein Sitzungstoken.
 *
 * @extends AbstractShopOrderImportService<PrestaShopOrder, PrestaShopConnection>
 */
class PrestaShopOrderImportService extends AbstractShopOrderImportService {
    private const TEMPLATES = [
        'order_canceled' => ShopOrderStatus::Cancelled,
        'payment_error' => ShopOrderStatus::Cancelled,
        'refund' => ShopOrderStatus::Refunded,
    ];

    public function pluginId(): string {
        return PrestaShopPlugin::ID;
    }

    public function isConfigured(int $organizationId): bool {
        return app(PrestaShopClientFactory::class)->isConfigured($organizationId);
    }

    public function orderUrl(Model $order): ?string {
        return null;
    }

    protected function orderModel(): string {
        return PrestaShopOrder::class;
    }

    protected function connectionModel(): string {
        return PrestaShopConnection::class;
    }

    protected function fetch(Organization $organization, CarbonImmutable $since): iterable {
        $factory = app(PrestaShopClientFactory::class);
        $client = $factory->for((int) $organization->id);
        $timezone = $factory->shopTimezone((int) $organization->id)
            ?? $this->connection((int) $organization->id)->detected_timezone
            ?? Tz::ofOrganization($organization);
        $states = $client->orderStates();

        foreach ($client->orderPages(self::localFloor($since, $timezone)) as $page) {
            $customerIds = array_values(array_unique(array_filter(array_map(static fn (array $order): int => is_numeric($order['id_customer'] ?? null) ? (int) $order['id_customer'] : 0, $page))));
            $customers = $client->customers($customerIds);
            $refunds = $client->refundsByOrder(array_values(array_filter(array_map(static fn (array $order): int => is_numeric($order['id'] ?? null) ? (int) $order['id'] : 0, $page))));
            foreach ($page as $order) {
                yield $order + [
                    '_refunds' => $refunds === null ? null : ($refunds[(int) ($order['id'] ?? 0)] ?? []),
                    '_customer' => $customers[(int) ($order['id_customer'] ?? 0)] ?? null,
                    '_currency' => $client->currency(is_numeric($order['id_currency'] ?? null) ? (int) $order['id_currency'] : 0),
                    '_state' => $states[(int) ($order['current_state'] ?? 0)] ?? null,
                    '_timezone' => $timezone,
                ];
            }
        }
    }

    protected function existingRemoteIds(Organization $organization, array $ids): array {
        return app(PrestaShopClientFactory::class)->for((int) $organization->id)->existingOrderIds($ids);
    }

    protected function normalize(array $raw, Organization $organization): RemoteOrder {
        $id = is_scalar($raw['id'] ?? null) ? (string) $raw['id'] : '';
        if (! ctype_digit($id)) {
            throw new IncompleteRemoteReadException('invalid_order');
        }
        $timezone = is_string($raw['_timezone'] ?? null) ? $raw['_timezone'] : 'UTC';
        $customer = is_array($raw['_customer'] ?? null) ? $raw['_customer'] : [];
        $email = StringHelper::trimToNull($customer['email'] ?? null);
        $guest = (string) ($customer['is_guest'] ?? '0') === '1';
        $customerId = is_numeric($customer['id'] ?? null) ? (int) $customer['id'] : 0;
        $stateId = is_numeric($raw['current_state'] ?? null) ? (int) $raw['current_state'] : 0;
        $currency = CurrencyCode::tryFrom((string) ($raw['_currency'] ?? ''));
        $refunds = $raw['_refunds'] ?? null;

        return new RemoteOrder(
            id: $id,
            number: StringHelper::trimToNull($raw['reference'] ?? null),
            status: self::status(is_array($raw['_state'] ?? null) ? $raw['_state'] : null),
            remoteStatus: $stateId > 0 ? 'state:' . $stateId : null,
            currency: $currency,
            grossAmount: RemoteOrder::amount($raw['total_paid_tax_incl'] ?? null),
            netAmount: RemoteOrder::amount($raw['total_paid_tax_excl'] ?? null),
            orderedAt: self::date($raw['date_add'] ?? null, $timezone),
            modifiedAt: self::date($raw['date_upd'] ?? null, $timezone),
            buyerKey: $customerId > 0 && ! $guest ? 'customer:' . $customerId : ($email !== null ? 'guest:' . mb_strtolower($email) : null),
            buyerName: StringHelper::trimToNull(PersonNameHelper::join(['first_name' => $customer['firstname'] ?? null, 'last_name' => $customer['lastname'] ?? null])),
            buyerEmail: $email,
            buyerCompany: StringHelper::trimToNull($customer['company'] ?? null),
            items: self::items(data_get($raw, 'associations.order_rows')),
            // Ohne Leserecht auf `order_slip` bleibt der erstattete Betrag unbekannt.
            refundedAmount: is_array($refunds) ? Money::sum(array_map(static fn (string $amount): Money => Money::of($amount, $currency ?? CurrencyCode::Euro), array_filter($refunds, 'is_string')), $currency ?? CurrencyCode::Euro)->getAmount() : null,
        );
    }

    /** @param array<mixed>|null $state Merkmale aus {@see \App\Plugins\PrestaShop\Api\PrestaShopClient::orderStates()} */
    private static function status(?array $state): ShopOrderStatus {
        if ($state === null) {
            return ShopOrderStatus::Open;
        }
        $template = is_string($state['template'] ?? null) ? $state['template'] : '';

        return self::TEMPLATES[$template] ?? match (true) {
            ($state['shipped'] ?? false) === true => ShopOrderStatus::Completed,
            ($state['paid'] ?? false) === true || ($state['logable'] ?? false) === true => ShopOrderStatus::Processing,
            default => ShopOrderStatus::Open,
        };
    }

    /**
     * Untergrenze des Filters in Shop-Ortszeit: die früheste Ortszeit seit
     * `$since`. Nach der Rückstellung der Uhr wiederholt sich eine Stunde,
     * spätere Änderungen tragen dann eine kleinere Ortszeit als `$since`.
     */
    private static function localFloor(CarbonImmutable $since, string $timezone): string {
        $zone = new DateTimeZone($timezone);
        $floor = $since->setTimezone($zone)->format('Y-m-d H:i:s');
        foreach ($zone->getTransitions($since->getTimestamp(), CarbonImmutable::now()->getTimestamp()) ?: [] as $transition) {
            $local = CarbonImmutable::createFromTimestamp($transition['ts'], $zone)->format('Y-m-d H:i:s');
            $floor = min($floor, $local);
        }

        return $floor;
    }

    private static function date(mixed $value, string $timezone): ?CarbonImmutable {
        return is_string($value) && trim($value) !== '' && ! str_starts_with($value, '0000') ? CarbonImmutable::parse($value, $timezone)->utc() : null;
    }

    /** @return list<array{name: string, sku: ?string, quantity: string, total: ?string}> */
    private static function items(mixed $rows): array {
        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || ! is_string($row['product_name'] ?? null)) {
                continue;
            }
            $items[] = [
                'name' => mb_substr($row['product_name'], 0, 255),
                'sku' => StringHelper::trimToNull($row['product_reference'] ?? null),
                'quantity' => is_numeric($row['product_quantity'] ?? null) ? (string) $row['product_quantity'] : '0',
                'total' => null,
            ];
        }

        return $items;
    }
}
