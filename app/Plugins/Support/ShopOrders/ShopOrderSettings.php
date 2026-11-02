<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderSettings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

/**
 * Gemeinsame Zeilen des Einstellungsschemas der Shop-Importe (Feature 175):
 * Zeitfenster des ersten Abgleichs ({@see AbstractShopOrderImportService::initialWindowDays()}),
 * stornierte Bestellungen, Opt-in für private Adressen.
 */
final class ShopOrderSettings {
    /** @return list<array{key: string, label: string, type: string, default: mixed, help: string}> */
    public static function fields(): array {
        return [
            ['key' => 'initial_window_days', 'label' => (string) __('integration.shop_orders.settings.initial_window_days'), 'type' => 'number', 'default' => 30, 'help' => (string) __('integration.shop_orders.settings.initial_window_days_help')],
            ['key' => 'include_cancelled', 'label' => (string) __('integration.shop_orders.settings.include_cancelled'), 'type' => 'boolean', 'default' => false, 'help' => (string) __('integration.shop_orders.settings.include_cancelled_help')],
            ['key' => 'allow_private_network', 'label' => (string) __('integration.shop_orders.settings.allow_private_network'), 'type' => 'boolean', 'default' => false, 'help' => (string) __('integration.shop_orders.settings.allow_private_network_help')],
        ];
    }
}
