<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Console;

use App\Plugins\PrestaShop\PrestaShopPlugin;
use App\Plugins\PrestaShop\Services\PrestaShopOrderImportService;
use App\Plugins\Support\ShopOrders\ShopOrderSyncCommand;

/**
 * PrestaShop-Abgleich je Organisation (Scheduler `prestashop.sync` alle 15 Minuten, `prestashop.full_sync` täglich).
 *
 * @extends ShopOrderSyncCommand<\App\Plugins\PrestaShop\Models\PrestaShopOrder, \App\Plugins\PrestaShop\Models\PrestaShopConnection>
 */
class PrestaShopSyncCommand extends ShopOrderSyncCommand {
    protected $signature = 'prestashop:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Bestellungen im Zeitfenster}';

    protected $description = 'Importiert Bestellungen des verbundenen PrestaShop-Shops.';

    protected function pluginId(): string {
        return PrestaShopPlugin::ID;
    }

    protected function importService(): PrestaShopOrderImportService {
        return app(PrestaShopOrderImportService::class);
    }
}
