<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareCustomerOrdersPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware\Services;

use App\Plugins\Shopware\Models\ShopwareOrder;
use App\Plugins\Shopware\ShopwarePlugin;
use App\Plugins\Support\ShopOrders\ShopCustomerOrdersPanel;

final class ShopwareCustomerOrdersPanel extends ShopCustomerOrdersPanel {
    protected function pluginId(): string {
        return ShopwarePlugin::ID;
    }

    protected function orderModel(): string {
        return ShopwareOrder::class;
    }
}
