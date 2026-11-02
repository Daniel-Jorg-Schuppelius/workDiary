<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommercePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce;

use App\Plugins\AbstractPlugin;
use App\Plugins\Support\{ChecksConnectionHealth, SelfHostedSettings};
use App\Plugins\Support\ShopOrders\ShopOrderSettings;
use App\Plugins\WooCommerce\Api\WooCommerceClientFactory;
use App\Plugins\WooCommerce\Models\WooCommerceConnection;

/**
 * WooCommerce-Bestellimport (Feature 175, Phase 158): Bestellungen eines
 * Shops werden nur gelesen, gespiegelt und vorhandenen Kunden zugeordnet.
 * Gemeinsame Strecke aller Shops: {@see \App\Plugins\Support\ShopOrders\AbstractShopOrderImportService}.
 */
class WooCommercePlugin extends AbstractPlugin {
    use ChecksConnectionHealth;

    public const ID = 'woocommerce';

    public const SERVICE_PROVIDER = WooCommerceServiceProvider::class;

    public function name(): string {
        return 'WooCommerce';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('woocommerce::woocommerce.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            SelfHostedSettings::field(self::ID, 'base_url'),
            SelfHostedSettings::field(self::ID, 'consumer_key'),
            SelfHostedSettings::field(self::ID, 'consumer_secret', 'password', help: false),
            ...ShopOrderSettings::fields(),
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.woocommerce.index', 'label' => 'WooCommerce', 'icon' => 'storefront'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return WooCommerceConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return WooCommerceConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(WooCommerceClientFactory::class)->for($organizationId)->probe();
    }
}
