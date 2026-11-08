<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrestaShopPlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PrestaShop;

use App\Plugins\AbstractPlugin;
use App\Plugins\PrestaShop\Api\PrestaShopClientFactory;
use App\Plugins\PrestaShop\Models\PrestaShopConnection;
use App\Plugins\Support\{ChecksConnectionHealth, SelfHostedSettings};
use App\Plugins\Support\ShopOrders\ShopOrderSettings;

/**
 * PrestaShop-Bestellimport (Feature 175, Phase 158): Bestellungen eines
 * Shops werden nur gelesen, gespiegelt und vorhandenen Kunden zugeordnet.
 * Gemeinsame Strecke aller Shops: {@see \App\Plugins\Support\ShopOrders\AbstractShopOrderImportService}.
 */
class PrestaShopPlugin extends AbstractPlugin {
    use ChecksConnectionHealth;

    public const string ID = 'prestashop';

    public const string SERVICE_PROVIDER = PrestaShopServiceProvider::class;

    public function name(): string {
        return 'PrestaShop';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('prestashop::prestashop.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            SelfHostedSettings::field(self::ID, 'base_url'),
            SelfHostedSettings::field(self::ID, 'api_key', 'password'),
            SelfHostedSettings::field(self::ID, 'shop_timezone', required: false),
            ...ShopOrderSettings::fields(),
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.prestashop.index', 'label' => 'PrestaShop', 'icon' => 'storefront'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return PrestaShopConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return PrestaShopConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(PrestaShopClientFactory::class)->for($organizationId)->probe();
    }
}
