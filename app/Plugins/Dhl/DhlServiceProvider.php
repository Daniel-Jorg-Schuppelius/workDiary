<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DhlServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Dhl;

use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Shipping\ShippingProviderRegistry;

/**
 * Plugin-eigener ServiceProvider (Feature 059). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald DhlPlugin in der
 * Registry steht. Mergt die Config-Defaults und trägt den DHL-{@see DhlPlugin}
 * als {@see \App\Plugins\Contracts\ShippingProvider} in die
 * {@see ShippingProviderRegistry} ein, damit der ShipmentService ihn über den
 * Carrier-Schlüssel `dhl` auflöst.
 */
class DhlServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return DhlPlugin::ID;
    }

    protected function bootPlugin(): void {
        $this->app->make(ShippingProviderRegistry::class)->register($this->app->make(DhlPlugin::class));
    }
}
