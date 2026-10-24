<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareSyncCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware\Console;

use App\Plugins\Shopware\Services\ShopwareOrderImportService;
use App\Plugins\Shopware\ShopwarePlugin;
use App\Plugins\Support\ShopOrders\ShopOrderSyncCommand;

/**
 * Shopware-Abgleich je Organisation (Scheduler `shopware.sync` alle 15 Minuten, `shopware.full_sync` täglich).
 *
 * @extends ShopOrderSyncCommand<\App\Plugins\Shopware\Models\ShopwareOrder, \App\Plugins\Shopware\Models\ShopwareConnection>
 */
class ShopwareSyncCommand extends ShopOrderSyncCommand {
    protected $signature = 'shopware:sync ' . self::ORGANIZATION_OPTION . '
        {--full : Vollabgleich aller Bestellungen im Zeitfenster}';

    protected $description = 'Importiert Bestellungen des verbundenen Shopware-Shops.';

    protected function pluginId(): string {
        return ShopwarePlugin::ID;
    }

    protected function importService(): ShopwareOrderImportService {
        return app(ShopwareOrderImportService::class);
    }
}
