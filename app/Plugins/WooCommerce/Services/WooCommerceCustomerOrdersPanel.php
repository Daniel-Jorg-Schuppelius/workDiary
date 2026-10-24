<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceCustomerOrdersPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Services;

use App\Plugins\Support\ShopOrders\ShopCustomerOrdersPanel;
use App\Plugins\WooCommerce\Models\WooCommerceOrder;
use App\Plugins\WooCommerce\WooCommercePlugin;

final class WooCommerceCustomerOrdersPanel extends ShopCustomerOrdersPanel {
    protected function pluginId(): string {
        return WooCommercePlugin::ID;
    }

    protected function orderModel(): string {
        return WooCommerceOrder::class;
    }
}
