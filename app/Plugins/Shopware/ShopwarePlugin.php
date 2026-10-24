<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopwarePlugin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Shopware;

use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\Shopware\Api\ShopwareClientFactory;
use App\Plugins\Shopware\Models\ShopwareConnection;
use App\Plugins\Support\ApiHealthProbe;

/**
 * Shopware-6-Bestellimport (Feature 175, Phase 158): Bestellungen eines
 * Shops werden nur gelesen, gespiegelt und vorhandenen Kunden zugeordnet.
 * Gemeinsame Strecke aller Shops: {@see \App\Plugins\Support\ShopOrders\AbstractShopOrderImportService}.
 */
class ShopwarePlugin extends AbstractPlugin {
    public const ID = 'shopware';

    public const SERVICE_PROVIDER = ShopwareServiceProvider::class;

    public function name(): string {
        return 'Shopware';
    }

    public function version(): string {
        return '0.1.0';
    }

    public function description(): string {
        return (string) __('shopware::shopware.plugin.description');
    }

    public function settingsSchema(): array {
        return [
            ['key' => 'base_url', 'label' => __('shopware::shopware.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('shopware::shopware.settings.base_url_help')],
            ['key' => 'client_id', 'label' => __('shopware::shopware.settings.client_id'), 'type' => 'text', 'required' => true, 'help' => __('shopware::shopware.settings.client_id_help')],
            ['key' => 'client_secret', 'label' => __('shopware::shopware.settings.client_secret'), 'type' => 'password', 'required' => true],
            ['key' => 'initial_window_days', 'label' => __('integration.shop_orders.settings.initial_window_days'), 'type' => 'number', 'default' => 30, 'help' => __('integration.shop_orders.settings.initial_window_days_help')],
            ['key' => 'include_cancelled', 'label' => __('integration.shop_orders.settings.include_cancelled'), 'type' => 'boolean', 'default' => false, 'help' => __('integration.shop_orders.settings.include_cancelled_help')],
            ['key' => 'allow_private_network', 'label' => __('integration.shop_orders.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('integration.shop_orders.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.shopware.index', 'label' => 'Shopware', 'icon' => 'storefront'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! ShopwareConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('shopware::shopware.health.not_configured'), 'not_configured');
        }
        $connection = ShopwareConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof ShopwareConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('shopware::shopware.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(ShopwareClientFactory::class)->for((int) $organization->id)->probe());
    }
}
