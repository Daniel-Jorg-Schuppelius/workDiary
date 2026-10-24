<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Services;

use App\Plugins\Support\ShopOrders\ShopOrderRetentionPolicies;
use App\Plugins\WooCommerce\Models\WooCommerceOrder;

final class WooCommerceRetentionPolicies extends ShopOrderRetentionPolicies {
    protected function orderModel(): string {
        return WooCommerceOrder::class;
    }
}
