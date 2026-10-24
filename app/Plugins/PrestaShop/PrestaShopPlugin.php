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

use App\Plugins\{AbstractPlugin, PluginHealth};
use App\Plugins\PrestaShop\Api\PrestaShopClientFactory;
use App\Plugins\PrestaShop\Models\PrestaShopConnection;
use App\Plugins\Support\ApiHealthProbe;

/**
 * PrestaShop-Bestellimport (Feature 175, Phase 158): Bestellungen eines
 * Shops werden nur gelesen, gespiegelt und vorhandenen Kunden zugeordnet.
 * Gemeinsame Strecke aller Shops: {@see \App\Plugins\Support\ShopOrders\AbstractShopOrderImportService}.
 */
class PrestaShopPlugin extends AbstractPlugin {
    public const ID = 'prestashop';

    public const SERVICE_PROVIDER = PrestaShopServiceProvider::class;

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
            ['key' => 'base_url', 'label' => __('prestashop::prestashop.settings.base_url'), 'type' => 'text', 'required' => true, 'help' => __('prestashop::prestashop.settings.base_url_help')],
            ['key' => 'api_key', 'label' => __('prestashop::prestashop.settings.api_key'), 'type' => 'password', 'required' => true, 'help' => __('prestashop::prestashop.settings.api_key_help')],
            ['key' => 'shop_timezone', 'label' => __('prestashop::prestashop.settings.shop_timezone'), 'type' => 'text', 'required' => false, 'help' => __('prestashop::prestashop.settings.shop_timezone_help')],
            ['key' => 'initial_window_days', 'label' => __('integration.shop_orders.settings.initial_window_days'), 'type' => 'number', 'default' => 30, 'help' => __('integration.shop_orders.settings.initial_window_days_help')],
            ['key' => 'include_cancelled', 'label' => __('integration.shop_orders.settings.include_cancelled'), 'type' => 'boolean', 'default' => false, 'help' => __('integration.shop_orders.settings.include_cancelled_help')],
            ['key' => 'allow_private_network', 'label' => __('integration.shop_orders.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => __('integration.shop_orders.settings.allow_private_network_help')],
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.prestashop.index', 'label' => 'PrestaShop', 'icon' => 'storefront'];
    }

    public function healthCheck(): PluginHealth {
        $organization = $this->healthOrgContext();
        if ($organization instanceof PluginHealth) {
            return $organization;
        }
        if (! PrestaShopConfig::isConfigured((int) $organization->id)) {
            return PluginHealth::degraded((string) __('prestashop::prestashop.health.not_configured'), 'not_configured');
        }
        $connection = PrestaShopConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof PrestaShopConnection || ! $connection->isReady()) {
            return PluginHealth::degraded((string) __('prestashop::prestashop.health.not_connected'), 'not_connected');
        }

        return ApiHealthProbe::probe($this->name(), fn () => app(PrestaShopClientFactory::class)->for((int) $organization->id)->probe());
    }
}
