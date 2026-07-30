<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlWawiServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\JtlWawi;

use App\Models\Organization;
use App\Plugins\JtlWawi\Console\JtlSyncCommand;
use App\Plugins\JtlWawi\Services\{JtlStockReader, JtlWawiInventoryProvider, JtlWawiOutboxDispatcher};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Inventory\{ExternalInventoryDispatcherResolver, InventoryLedger, InventoryProviderResolver};

/**
 * Bootet das JTL-Wawi-Plugin (Feature 078): Routen, Views, Console-Command
 * und die beiden Bestands-Registrierungen — Outbox-Dispatcher
 * (Schreibzustellung) und Provider-Factory (Lese-/Buchungsvertrag) an den
 * Singleton-Resolvern des Lagerkerns.
 */
class JtlWawiServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return JtlWawiPlugin::ID;
    }

    protected function registerPlugin(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([JtlSyncCommand::class]);
        }
    }

    protected function bootPlugin(): void {
        $this->app->make(ExternalInventoryDispatcherResolver::class)
            ->register($this->app->make(JtlWawiOutboxDispatcher::class));

        $this->app->make(InventoryProviderResolver::class)->registerExternal(
            JtlWawiPlugin::ID,
            fn (Organization $organization): JtlWawiInventoryProvider => new JtlWawiInventoryProvider(
                $organization,
                $this->app->make(JtlStockReader::class),
                $this->app->make(InventoryLedger::class),
            ),
        );
    }
}
