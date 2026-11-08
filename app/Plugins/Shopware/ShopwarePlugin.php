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

use App\Plugins\AbstractPlugin;
use App\Plugins\Shopware\Api\ShopwareClientFactory;
use App\Plugins\Shopware\Models\ShopwareConnection;
use App\Plugins\Support\{ChecksConnectionHealth, SelfHostedSettings};
use App\Plugins\Support\ShopOrders\ShopOrderSettings;

/**
 * Shopware-6-Bestellimport (Feature 175, Phase 158): Bestellungen eines
 * Shops werden nur gelesen, gespiegelt und vorhandenen Kunden zugeordnet.
 * Gemeinsame Strecke aller Shops: {@see \App\Plugins\Support\ShopOrders\AbstractShopOrderImportService}.
 */
class ShopwarePlugin extends AbstractPlugin {
    use ChecksConnectionHealth;

    public const string ID = 'shopware';

    public const string SERVICE_PROVIDER = ShopwareServiceProvider::class;

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
            SelfHostedSettings::field(self::ID, 'base_url'),
            SelfHostedSettings::field(self::ID, 'client_id'),
            SelfHostedSettings::field(self::ID, 'client_secret', 'password', help: false),
            ...ShopOrderSettings::fields(),
        ];
    }

    public function capabilities(): array {
        return [];
    }

    public function adminPanel(): ?array {
        return ['route' => 'admin.shopware.index', 'label' => 'Shopware', 'icon' => 'storefront'];
    }

    protected function healthConfigured(int $organizationId): bool {
        return ShopwareConfig::isConfigured($organizationId);
    }

    protected function healthConnectionModel(): string {
        return ShopwareConnection::class;
    }

    protected function healthProbe(int $organizationId): void {
        app(ShopwareClientFactory::class)->for($organizationId)->probe();
    }
}
