<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwareAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware\Http\Controllers;

use App\Plugins\Shopware\Api\ShopwareClientFactory;
use App\Plugins\Shopware\Models\{ShopwareConnection, ShopwareOrder};
use App\Plugins\Shopware\Services\ShopwareOrderImportService;
use App\Plugins\Shopware\ShopwareConfig;
use App\Plugins\Support\ShopOrders\ShopOrderAdminController;
use App\Plugins\Support\TaskSync\SelfHostedInstance;

/**
 * Shopware-Seite (MVP-1326/1329).
 *
 * @extends ShopOrderAdminController<ShopwareOrder, ShopwareConnection>
 */
class ShopwareAdminController extends ShopOrderAdminController {
    protected function importService(): ShopwareOrderImportService {
        return app(ShopwareOrderImportService::class);
    }

    protected function connectionModel(): string {
        return ShopwareConnection::class;
    }

    protected function orderModel(): string {
        return ShopwareOrder::class;
    }

    protected function providerName(): string {
        return 'Shopware';
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) ShopwareConfig::resolve($organizationId)['base_url'];
        $client = app(ShopwareClientFactory::class)->for($organizationId);
        $client->probe();
        $version = $client->version();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl) . ($version !== null ? ' (Shopware ' . $version . ')' : ''),
            'source_url' => $baseUrl,
            'account_name' => null,
        ];
    }
}
