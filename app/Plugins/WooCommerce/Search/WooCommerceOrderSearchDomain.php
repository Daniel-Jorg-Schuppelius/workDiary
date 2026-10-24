<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceOrderSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Search;

use App\Plugins\Support\ShopOrders\ShopOrderSearchDomain;
use App\Plugins\WooCommerce\Models\WooCommerceOrder;
use App\Plugins\WooCommerce\WooCommercePlugin;

final class WooCommerceOrderSearchDomain extends ShopOrderSearchDomain {
    protected function pluginId(): string {
        return WooCommercePlugin::ID;
    }

    protected function orderModel(): string {
        return WooCommerceOrder::class;
    }
}
