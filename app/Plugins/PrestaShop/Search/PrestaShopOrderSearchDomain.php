<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopOrderSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Search;

use App\Plugins\PrestaShop\Models\PrestaShopOrder;
use App\Plugins\PrestaShop\PrestaShopPlugin;
use App\Plugins\Support\ShopOrders\ShopOrderSearchDomain;

final class PrestaShopOrderSearchDomain extends ShopOrderSearchDomain {
    protected function pluginId(): string {
        return PrestaShopPlugin::ID;
    }

    protected function orderModel(): string {
        return PrestaShopOrder::class;
    }
}
