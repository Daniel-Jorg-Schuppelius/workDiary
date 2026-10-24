<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce\Http\Controllers;

use App\Plugins\Support\ShopOrders\ShopOrderAdminController;
use App\Plugins\Support\TaskSync\SelfHostedInstance;
use App\Plugins\WooCommerce\Api\WooCommerceClientFactory;
use App\Plugins\WooCommerce\Models\{WooCommerceConnection, WooCommerceOrder};
use App\Plugins\WooCommerce\Services\WooCommerceOrderImportService;
use App\Plugins\WooCommerce\WooCommerceConfig;

/**
 * WooCommerce-Seite (MVP-1325/1329).
 *
 * @extends ShopOrderAdminController<WooCommerceOrder, WooCommerceConnection>
 */
class WooCommerceAdminController extends ShopOrderAdminController {
    protected function importService(): WooCommerceOrderImportService {
        return app(WooCommerceOrderImportService::class);
    }

    protected function connectionModel(): string {
        return WooCommerceConnection::class;
    }

    protected function orderModel(): string {
        return WooCommerceOrder::class;
    }

    protected function providerName(): string {
        return 'WooCommerce';
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) WooCommerceConfig::resolve($organizationId)['base_url'];
        $client = app(WooCommerceClientFactory::class)->for($organizationId);
        $client->probe();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => $client->storeName() ?? SelfHostedInstance::name($baseUrl),
            'source_url' => $baseUrl,
            'account_name' => null,
        ];
    }
}
