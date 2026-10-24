<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WooCommerceServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\WooCommerce;

use App\Modules\ModuleRegistry;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Search\Contracts\SearchDomain;

class WooCommerceServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return WooCommercePlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\WooCommerceSyncCommand::class]);
    }

    protected function bootPlugin(): void {
        $registry = $this->app->make(ModuleRegistry::class);
        $registry->contribute(SearchDomain::class, Search\WooCommerceOrderSearchDomain::class);
        $registry->contribute(CockpitPanel::class, Services\WooCommerceCustomerOrdersPanel::class);
        $registry->contribute(RetentionPolicyProvider::class, Services\WooCommerceRetentionPolicies::class);
    }
}
