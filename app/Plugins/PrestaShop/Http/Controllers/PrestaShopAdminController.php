<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop\Http\Controllers;

use App\Plugins\PrestaShop\Api\PrestaShopClientFactory;
use App\Plugins\PrestaShop\Models\{PrestaShopConnection, PrestaShopOrder};
use App\Plugins\PrestaShop\PrestaShopConfig;
use App\Plugins\PrestaShop\Services\PrestaShopOrderImportService;
use App\Plugins\Support\PluginApiException;
use App\Plugins\Support\ShopOrders\ShopOrderAdminController;
use App\Plugins\Support\TaskSync\{IncompleteRemoteReadException, SelfHostedInstance};

/**
 * PrestaShop-Seite (MVP-1327/1329).
 *
 * @extends ShopOrderAdminController<PrestaShopOrder, PrestaShopConnection>
 */
class PrestaShopAdminController extends ShopOrderAdminController {
    protected function importService(): PrestaShopOrderImportService {
        return app(PrestaShopOrderImportService::class);
    }

    protected function connectionModel(): string {
        return PrestaShopConnection::class;
    }

    protected function orderModel(): string {
        return PrestaShopOrder::class;
    }

    protected function providerName(): string {
        return 'PrestaShop';
    }

    protected function verifyConnection(int $organizationId): array {
        $baseUrl = (string) PrestaShopConfig::resolve($organizationId)['base_url'];
        app(PrestaShopClientFactory::class)->for($organizationId)->probe();

        return [
            'source_id' => SelfHostedInstance::key($baseUrl),
            'source_name' => SelfHostedInstance::name($baseUrl),
            'source_url' => $baseUrl,
            'account_name' => null,
        ];
    }

    protected function verifiedDetails(int $organizationId): array {
        try {
            return ['detected_timezone' => app(PrestaShopClientFactory::class)->for($organizationId)->shopTimezone()];
        } catch (PluginApiException|IncompleteRemoteReadException $e) {
            report($e);

            return [];
        }
    }
}
