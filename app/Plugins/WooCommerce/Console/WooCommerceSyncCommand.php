<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Console;

use App\Plugins\Support\ShopOrders\ShopOrderSyncCommand;
use App\Plugins\WooCommerce\Services\WooCommerceOrderImportService;
use App\Plugins\WooCommerce\WooCommercePlugin;

/**
 * WooCommerce-Abgleich je Organisation (Scheduler `woocommerce.sync` alle 15 Minuten, `woocommerce.full_sync` täglich).
 *
 * @extends ShopOrderSyncCommand<\App\Plugins\WooCommerce\Models\WooCommerceOrder, \App\Plugins\WooCommerce\Models\WooCommerceConnection>
 */
class WooCommerceSyncCommand extends ShopOrderSyncCommand {
    protected $signature = 'woocommerce:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Bestellungen im Zeitfenster}';

    protected $description = 'Importiert Bestellungen des verbundenen WooCommerce-Shops.';

    protected function pluginId(): string {
        return WooCommercePlugin::ID;
    }

    protected function importService(): WooCommerceOrderImportService {
        return app(WooCommerceOrderImportService::class);
    }
}
