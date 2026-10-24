<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Models;

use App\Enums\Integration\{MarketplaceInboxStatus, ShopOrderStatus};
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Customer\Customer;
use App\Plugins\Support\ShopOrders\{IsShopOrder, ShopOrder};
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Gespiegelte WooCommerce-Bestellung (Feature 175).
 *
 * @property int $id
 * @property int $organization_id
 * @property string $remote_order_id
 * @property string|null $order_number
 * @property ShopOrderStatus $status
 * @property string|null $remote_status_code
 * @property CurrencyCode|null $currency
 * @property Money|null $gross_amount
 * @property Money|null $net_amount
 * @property string|null $buyer_external_id
 * @property array{name: ?string, email: ?string, company: ?string}|null $buyer
 * @property list<array{name: string, sku: ?string, quantity: string, total: ?string}>|null $items
 * @property CarbonImmutable|null $ordered_at
 * @property CarbonImmutable|null $remote_modified_at
 * @property int|null $customer_id
 * @property MarketplaceInboxStatus $inbox_status
 * @property-read Customer|null $customer
 */
class WooCommerceOrder extends Model implements ShopOrder {
    use BelongsToOrganization;
    use IsShopOrder;

    protected $table = 'woocommerce_orders';
}
