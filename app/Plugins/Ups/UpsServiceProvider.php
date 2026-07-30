<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpsServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Ups;

use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Shipping\ShippingProviderRegistry;

/**
 * Plugin-eigener ServiceProvider (Feature 059 / Bauturbo A5). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald UpsPlugin in der
 * Registry steht. Mergt die Config-Defaults und trägt den {@see UpsPlugin}
 * als {@see \App\Plugins\Contracts\ShippingProvider} in die
 * {@see ShippingProviderRegistry} ein, damit der ShipmentService ihn über den
 * Carrier-Schlüssel `ups` auflöst.
 */
class UpsServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return UpsPlugin::ID;
    }

    protected function bootPlugin(): void {
        $this->app->make(ShippingProviderRegistry::class)->register($this->app->make(UpsPlugin::class));
    }
}
