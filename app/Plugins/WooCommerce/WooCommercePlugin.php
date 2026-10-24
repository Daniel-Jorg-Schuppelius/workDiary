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

use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Support\ApiHealthProbe;
use App\Plugins\WooCommerce\Api\WooCommerceClientFactory;
use App\Plugins\WooCommerce\Models\WooCommerceConnection;

/**
 * WooCommerce-Bestellimport (Feature 175, Phase 158): Bestellungen eines
 * Shops werden nur gelesen, gespiegelt und vorhandenen Kunden zugeordnet.
 * Gemeinsame Strecke aller Shops: {@see \App\Plugins\Support\ShopOrders\AbstractShopOrderImportService}.
 */
class WooCommercePlugin extends AbstractPlugin {
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
            ['key' => 'base_url', 'label' => __('woocommerce::woocommerce.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('woocommerce::woocommerce.settings.base_url_help')],
            ['key' => 'consumer_key', 'label' => __('woocommerce::woocommerce.settings.consumer_key'), 'type' => 'text', 'required' => true, 'help' => __('woocommerce::woocommerce.settings.consumer_key_help')],
            ['key' => 'consumer_secret', 'label' => __('woocommerce::woocommerce.settings.consumer_secret'), 'type' => 'password', 'required' => true],
            ['key' => 'initial_window_days', 'label' => __('integration.shop_orders.settings.initial_window_days'), 'type' => 'number', 'default' => 30, 'help' => __('integration.shop_orders.settings.initial_window_days_help')],
            ['key' => 'include_cancelled', 'label' => __('integration.shop_orders.settings.include_cancelled'), 'type' => 'boolean', 'default' => false, 'help' => __('integration.shop_orders.settings.include_cancelled_help')],
            ['key' => 'allow_private_network', 'label' => __('integration.shop_orders.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('integration.shop_orders.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.woocommerce.index', 'label' => 'WooCommerce', 'icon' => 'storefront'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! WooCommerceConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('woocommerce::woocommerce.health.not_configured'), 'not_configured');
        }
        $connection = WooCommerceConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof WooCommerceConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('woocommerce::woocommerce.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(WooCommerceClientFactory::class)->for((int) $organization->id)->probe());
    }
}
