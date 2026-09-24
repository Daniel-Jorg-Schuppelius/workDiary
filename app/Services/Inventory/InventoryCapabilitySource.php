<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryCapabilitySource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Contracts\Inventory\ExternalInventoryDispatcher;
use App\Plugins\Support\Contracts\PluginCapabilitySource;

/** Bestands-Rückschrieb als Plugin-Fähigkeit. */
final class InventoryCapabilitySource implements PluginCapabilitySource {
    public function __construct(private readonly ExternalInventoryDispatcherResolver $dispatchers) {}

    public function labelFor(string $pluginId): ?string {
        return $this->dispatchers->for($pluginId) instanceof ExternalInventoryDispatcher
            ? (string) __('plugins.capability.inventory')
            : null;
    }
}
